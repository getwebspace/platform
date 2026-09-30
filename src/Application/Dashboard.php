<?php declare(strict_types=1);

namespace App\Application;

use App\Domain\Casts\GuestBook\Status as GuestBookStatus;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Casts\Review\Status as ReviewStatus;
use App\Domain\Casts\Task\Status as TaskStatus;
use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Models\CatalogOrder;
use App\Domain\Models\File;
use App\Domain\Models\FormData;
use App\Domain\Models\GuestBook;
use App\Domain\Models\Reference;
use App\Domain\Models\Review;
use App\Domain\Models\Task;
use App\Domain\Models\User;
use App\Domain\Traits\HasParameters;
use Illuminate\Cache\ArrayStore as ArrayCache;
use Illuminate\Database\Connection as DataBase;
use Psr\Container\ContainerInterface;

/**
 * Site metrics: shared by the admin main page and MCP site_overview
 */
class Dashboard
{
    use HasParameters;

    protected ContainerInterface $container;

    protected ArrayCache $arrayCache;

    protected DataBase $db;

    final public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->arrayCache = $container->get(ArrayCache::class);
        $this->db = $container->get(DataBase::class);
    }

    /**
     * Sections turned on in the site parameters
     */
    public function enabled(): array
    {
        return [
            'publication' => $this->parameter('publication_is_enabled', 'yes') !== 'no',
            'page' => $this->parameter('page_is_enabled', 'yes') !== 'no',
            'catalog' => $this->parameter('catalog_is_enabled', 'yes') !== 'no',
            'guestbook' => $this->parameter('guestbook_is_enabled', 'yes') !== 'no',
            'form' => $this->parameter('form_is_enabled', 'yes') !== 'no',
            'file' => $this->parameter('file_is_enabled', 'yes') === 'yes',
            'review' => $this->parameter('review_product_is_enabled', 'no') === 'yes'
                || $this->parameter('review_publication_is_enabled', 'no') === 'yes',
        ];
    }

    /**
     * Actionable queues - things the administrator most likely logged in to deal with.
     */
    public function attention(array $enabled): array
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
    public function kpi(array $enabled, \Carbon\Carbon $weekAgo): array
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
     * 30-day daily revenue / order counts, aggregated in SQL.
     *
     * @return array{total: float, orders: int, today_orders: int, avg_check: float, daily: array<int, array{date: string, sum: float, count: int}>}
     */
    public function revenue(): array
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

        for ($i = 0; $i < 30; ++$i) {
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
