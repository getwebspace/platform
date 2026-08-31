<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Casts\GuestBook\Status as GuestBookStatus;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Casts\Review\Status as ReviewStatus;
use App\Domain\Casts\Task\Status as TaskStatus;
use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogOrder;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\File;
use App\Domain\Models\Form;
use App\Domain\Models\FormData;
use App\Domain\Models\GuestBook;
use App\Domain\Models\Page;
use App\Domain\Models\Publication;
use App\Domain\Models\Reference;
use App\Domain\Models\Review;
use App\Domain\Models\Task;
use App\Domain\Models\User;

class MainPageAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        /** @var User $user */
        $user = $this->request->getAttribute('user', false);

        $enabled = [
            'publication' => $this->parameter('publication_is_enabled', 'yes') !== 'no',
            'page' => $this->parameter('page_is_enabled', 'yes') !== 'no',
            'catalog' => $this->parameter('catalog_is_enabled', 'yes') !== 'no',
            'guestbook' => $this->parameter('guestbook_is_enabled', 'yes') !== 'no',
            'form' => $this->parameter('form_is_enabled', 'yes') !== 'no',
            'file' => $this->parameter('file_is_enabled', 'yes') === 'yes',
            'review' => $this->parameter('review_product_is_enabled', 'no') === 'yes'
                || $this->parameter('review_publication_is_enabled', 'no') === 'yes',
        ];

        $weekAgo = datetime()->subDays(7);

        return $this->respondWithTemplate('cup/main/index.twig', [
            'enabled' => $enabled,
            'notepad' => $this->parameter('notepad_' . $user->username, ''),
            'attention' => $this->getAttention($enabled),
            'kpi' => $this->getKpi($enabled, $weekAgo),
            'activity' => $this->getActivity($enabled),
            'revenue' => $enabled['catalog'] ? $this->getRevenue() : null,
            'stats' => [
                'pages' => Page::count(),
                'users' => User::where('status', UserStatus::WORK)->count(),
                'publications' => Publication::count(),
                'guestbook' => GuestBook::count(),
                'catalog' => [
                    'category' => CatalogCategory::count(),
                    'product' => CatalogProduct::count(),
                    'order' => CatalogOrder::count(),
                ],
                'forms' => Form::count(),
                'files' => File::count(),
            ],
            'properties' => [
                'version' => [
                    'branch' => ($_ENV['COMMIT_BRANCH'] ?? 'other'),
                    'commit' => ($_ENV['COMMIT_SHA'] ?? false),
                ],
                'os' => @implode(' ', [php_uname('s'), php_uname('r'), php_uname('m')]),
                'php' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'disable_functions' => ini_get('disable_functions'),
                'disable_classes' => ini_get('disable_classes'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'max_file_uploads' => ini_get('max_file_uploads'),
            ],
        ]);
    }

    /**
     * Actionable queues - things the administrator most likely logged in to deal with.
     */
    private function getAttention(array $enabled): array
    {
        $attention = [
            'users' => User::where('status', UserStatus::CONFIRMATION)->count(),
            'tasks_error' => Task::where('status', TaskStatus::FAIL)->count(),
            'tasks_active' => Task::whereIn('status', [TaskStatus::QUEUE, TaskStatus::WORK])->count(),
        ];

        if ($enabled['guestbook']) {
            $attention['guestbook'] = GuestBook::where('status', GuestBookStatus::MODERATE)->count();
        }

        if ($enabled['review']) {
            $attention['review'] = Review::where('status', ReviewStatus::MODERATE)->count();
        }

        if ($enabled['catalog']) {
            // "not yet handled" = no status assigned, or still in the very first order status
            $firstStatus = Reference::query()
                ->where('type', ReferenceType::ORDER_STATUS)
                ->where('status', true)
                ->orderBy('order')
                ->value('uuid');

            $attention['orders'] = CatalogOrder::where(function ($query) use ($firstStatus): void {
                $query->whereNull('status_uuid');

                if ($firstStatus) {
                    $query->orWhere('status_uuid', $firstStatus);
                }
            })->count();
        }

        return $attention;
    }

    /**
     * Headline numbers for the tiles row.
     */
    private function getKpi(array $enabled, \Carbon\Carbon $weekAgo): array
    {
        $kpi = [
            'users_week' => User::where('register', '>=', $weekAgo)->count(),
            'users_total' => User::where('status', UserStatus::WORK)->count(),
        ];

        if ($enabled['form']) {
            $kpi['form_data_week'] = FormData::where('date', '>=', $weekAgo)->count();
            $kpi['form_data_total'] = FormData::count();
        }

        if ($enabled['guestbook']) {
            $kpi['guestbook_week'] = GuestBook::where('date', '>=', $weekAgo)->count();
        }

        if ($enabled['file']) {
            $kpi['files_total'] = File::count();
            $kpi['files_size'] = (int) File::sum('size');
        }

        return $kpi;
    }

    /**
     * Recent rows per section for the "latest activity" cards.
     */
    private function getActivity(array $enabled): array
    {
        $activity = [
            'users' => User::orderByDesc('register')->limit(6)->get(),
        ];

        if ($enabled['catalog']) {
            $activity['orders'] = CatalogOrder::with(['status', 'products'])
                ->orderByDesc('date')
                ->limit(6)
                ->get();
        }

        if ($enabled['form']) {
            $activity['forms'] = FormData::with('form')
                ->orderByDesc('date')
                ->limit(6)
                ->get();
        }

        if ($enabled['guestbook']) {
            $activity['guestbook'] = GuestBook::orderByDesc('date')->limit(6)->get();
        }

        if ($enabled['publication']) {
            $activity['publications'] = Publication::orderByDesc('date')->limit(6)->get();
        }

        return $activity;
    }

    /**
     * 30-day daily revenue / order counts, aggregated in SQL.
     *
     * @return array{total: float, orders: int, today_orders: int, avg_check: float, daily: array<int, array{date: string, sum: float, count: int}>}
     */
    private function getRevenue(): array
    {
        $rows = $this->db
            ->table('catalog_order as co')
            ->select(
                $this->db->raw('DATE(co.date) as date'),
                $this->db->raw('COUNT(DISTINCT co.uuid) as order_count'),
                $this->db->raw('SUM(
                    CASE
                        WHEN cop.tax_included = false THEN (cop.price * (1 + cop.tax / 100) - cop.discount) * cop.count
                        ELSE (cop.price - cop.discount) * cop.count
                    END
                ) as sum')
            )
            ->leftJoin('catalog_order_product as cop', 'co.uuid', '=', 'cop.order_uuid')
            ->where('co.date', '>=', datetime()->subDays(29)->startOfDay())
            ->groupBy($this->db->raw('DATE(co.date)'))
            ->get()
            ->keyBy('date');

        $daily = [];
        $total = 0.0;
        $orders = 0;
        $todayOrders = 0;
        $today = datetime()->format('Y-m-d');
        $cursor = datetime()->subDays(29)->startOfDay();

        for ($i = 0; $i < 30; $i++) {
            $key = $cursor->format('Y-m-d');
            $row = $rows->get($key);
            $sum = $row ? (float) $row->sum : 0.0;
            $count = $row ? (int) $row->order_count : 0;

            // keys match what assets/js/cup/script.js#orders_revenue expects
            $daily[] = [
                'date' => $key,
                'order_count' => $count,
                'sum' => round($sum, 2),
                'average_check' => $count > 0 ? round($sum / $count, 2) : 0,
            ];
            $total += $sum;
            $orders += $count;

            if ($key === $today) {
                $todayOrders = $count;
            }

            $cursor->addDay();
        }

        return [
            'total' => round($total, 2),
            'orders' => $orders,
            'today_orders' => $todayOrders,
            'avg_check' => $orders > 0 ? round($total / $orders, 2) : 0.0,
            'daily' => $daily,
        ];
    }
}
