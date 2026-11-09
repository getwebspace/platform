<?php declare(strict_types=1);

namespace App\Domain\McpTools\Concerns;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Money of the orders, counted the way the model does it (Application\Dashboard::lineSql)
 */
trait SalesQueries
{
    /**
     * SQL of one line of an order, counted by the same formula as the model does
     */
    protected function lineSum(): string
    {
        return \App\Application\Dashboard::lineSql('cop');
    }

    /**
     * Order lines of a period (orders as co, lines as cop)
     *
     * @param string[] $statuses uuids of order statuses, empty - any
     */
    protected function lines(\Carbon\Carbon $from, \Carbon\Carbon $to, array $statuses = []): Builder
    {
        $query = $this->container->get(Connection::class)
            ->table('catalog_order as co')
            ->join('catalog_order_product as cop', 'co.uuid', '=', 'cop.order_uuid')
            ->where('co.date', '>=', $from->format('Y-m-d H:i:s'))
            ->where('co.date', '<=', $to->format('Y-m-d H:i:s'));

        if ($statuses) {
            $query->whereIn('co.status_uuid', $statuses);
        }

        return $query;
    }
}
