<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog;

use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class CategoryStatisticAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/catalog/statistic.twig', [
            'orders_revenue' => $this->getOrdersRevenueStats(),
            'top_products' => $this->getTopSoldProducts(),
            'top_buyers' => $this->getTopBuyers(),
        ]);
    }

    // catalog orders revenue stats
    private function getOrdersRevenueStats(): \Illuminate\Support\Collection
    {
        // sub query
        $orderSumsSubquery = $this->db
            ->table('catalog_order as co_inner')
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
        $orders = $this->db
            ->table('catalog_order as co')
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
            ->leftJoinSub($orderSumsSubquery, 'order_sums', function ($join): void {
                $join->on('co.uuid', '=', 'order_sums.uuid');
            })
            ->where('co.date', '>=', Carbon::now()->subDays(30))
            ->groupBy($this->db->raw('DATE(co.date)'))
            ->orderBy($this->db->raw('DATE(co.date)'))
            ->get();

        if ($orders->isNotEmpty()) {
            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();
            $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate->addDay());

            $orders = $orders->keyBy('date');

            foreach ($period as $date) {
                $formattedDate = $date->format('Y-m-d');

                if (!$orders->has($formattedDate)) {
                    $orders->put($formattedDate, (object) [
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

    private function getTopSoldProducts(): \Illuminate\Support\Collection
    {
        return $this->db
            ->table('catalog_order as co')
            ->leftJoin('catalog_order_product as cop', 'co.uuid', '=', 'cop.order_uuid')
            ->leftJoin('catalog_product as cp', 'cp.uuid', '=', 'cop.product_uuid')
            ->select('cp.uuid', 'cp.title', $this->db->raw('SUM(cop.count) as total_sold'),
                $this->db->raw('SUM(CASE 
                        WHEN cop.tax_included = false THEN (cop.price + cop.tax - cop.discount) * cop.count
                        ELSE (cop.price - cop.discount) * cop.count
                    END) as total_revenue'))
            ->where('co.date', '>=', Carbon::now()->subDays(30))
            ->where('cp.type', '=', 'product')
            ->groupBy('cp.uuid', 'cp.title')
            ->orderBy('total_sold', 'desc')
            ->orderBy('total_revenue', 'desc')
            ->limit(10)
            ->get();
    }

    private function getTopBuyers(): \Illuminate\Support\Collection
    {
        $connection = $this->db->getPDO()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($connection === 'sqlite') {
            $concat = 'email || "_" || phone';
        } else {
            $concat = 'CONCAT(email, "_", phone)';
        }

        $subquery = $this->db
            ->table('catalog_order')
            ->select($this->db->raw('COALESCE(user_uuid, ' . $concat . ') AS identifier, delivery, uuid'))
            ->where('date', '>=', Carbon::now()->subDays(30));

        return $this->db
            ->table($this->db->raw("({$subquery->toSql()}) as combined"))
            ->mergeBindings($subquery)
            ->select('identifier', 'delivery', $this->db->raw('COUNT(uuid) AS order_count'))
            ->groupBy('identifier')
            ->orderBy('order_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                if (!Uuid::isValid($item->identifier)) {
                    $item->identifier = null;
                }
                $item->delivery = json_decode($item->delivery, true);

                return $item;
            });
    }
}
