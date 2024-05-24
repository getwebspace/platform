<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Models\User;
use Carbon\Carbon;
use DateInterval;
use DatePeriod;

class MainPageAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        /** @var User $user */
        $user = $this->request->getAttribute('user', false);

        return $this->respondWithTemplate('cup/layout.twig', [
            'notepad' => $this->parameter('notepad_' . $user->username, ''),
            'stats' => [
                'pages' => \App\Domain\Models\Page::count(),
                'users' => \App\Domain\Models\User::where(['status' => \App\Domain\Casts\User\Status::WORK])->count(),
                'publications' => \App\Domain\Models\Publication::count(),
                'guestbook' => \App\Domain\Models\GuestBook::count(),
                'catalog' => [
                    'category' => \App\Domain\Models\CatalogCategory::count(),
                    'product' => \App\Domain\Models\CatalogProduct::count(),
                    'order' => \App\Domain\Models\CatalogOrder::count(),
                ],
                'forms' => \App\Domain\Models\Form::count(),
                'files' => \App\Domain\Models\File::count(),
            ],
            'chart' => $this->getStats(),
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

    // catalog order stats
    private function getStats()
    {
        // sub query
        $orderSumsSubquery = $this->db->table('catalog_order as co_inner')
            ->select(
                'co_inner.uuid',
                $this->db->raw('SUM(
                    CASE
                        WHEN cop_inner.tax_included = false THEN (cop_inner.price + cop_inner.tax - cop_inner.discount) * cop_inner.count
                        ELSE (cop_inner.price - cop_inner.discount) * cop_inner.count
                    END
                ) as sum')
            )
            ->leftJoin('catalog_order_product as cop_inner', 'co_inner.uuid', '=', 'cop_inner.order_uuid')
            ->groupBy('co_inner.uuid');

        // main query
        $orders = $this->db->table('catalog_order as co')
            ->select(
                $this->db->raw('DATE(co.date) as date'),
                $this->db->raw('COUNT(DISTINCT co.uuid) as order_count'),
                $this->db->raw('SUM(
                    CASE
                        WHEN cop.tax_included = false THEN (cop.price + cop.tax - cop.discount) * cop.count
                        ELSE (cop.price - cop.discount) * cop.count
                    END
                ) as sum'),
                $this->db->raw('ROUND(AVG(order_sums.sum)) as average_check')
            )
            ->leftJoin('catalog_order_product as cop', 'co.uuid', '=', 'cop.order_uuid')
            ->leftJoin('catalog_product as cp', 'cop.product_uuid', '=', 'cp.uuid')
            ->leftJoinSub($orderSumsSubquery, 'order_sums', function($join) {
                $join->on('co.uuid', '=', 'order_sums.uuid');
            })
            ->where('co.date', '>=', Carbon::now()->subDays(30))
            ->groupBy($this->db->raw('DATE(co.date)'))
            ->orderBy($this->db->raw('DATE(co.date)'))
            ->get();

        if ($orders->isNotEmpty()) {
            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();
            $period = new DatePeriod($startDate, new DateInterval('P1D'), $endDate->addDay());

            $orders = $orders->keyBy('date');

            foreach ($period as $date) {
                $formattedDate = $date->format('Y-m-d');

                if (!$orders->has($formattedDate)) {
                    $orders->put($formattedDate, (object)[
                        'date' => $formattedDate,
                        'order_count' => 0,
                        'sum' => 0,
                        'average_check' => 0,
                    ]);
                }
            }

            $orders = $orders->sortBy('date')->values();
        }

        return $orders;
    }
}
