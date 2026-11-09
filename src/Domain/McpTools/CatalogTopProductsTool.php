<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\McpTools\Concerns\ParsesPeriod;
use App\Domain\McpTools\Concerns\SalesQueries;

class CatalogTopProductsTool extends AbstractMcpTool
{
    use ParsesPeriod;
    use SalesQueries;

    public const NAME = 'catalog_top_products';
    public const TITLE = 'Best selling products';
    public const DESCRIPTION = 'Products that sold best in a period (30 days by default), by revenue, quantity or number of orders. A product that was deleted since is still listed as "deleted product". '
        . 'Shows the top only; for the whole picture use catalog_sales_report.';
    public const PRIVATE = true;
    public const SCOPE = 'catalog/order';

    private const SORTS = ['revenue', 'quantity', 'orders'];

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $this->periodSchema() + [
                'sort' => ['type' => 'string', 'enum' => self::SORTS, 'default' => 'revenue'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => static::LIMIT_MAX, 'default' => 10],
            ],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $sort = $args['sort'] ?? 'revenue';

        if (!in_array($sort, self::SORTS, true)) {
            throw new \InvalidArgumentException('Field sort must be one of: ' . implode(', ', self::SORTS));
        }

        [$from, $to] = $this->period($args);
        $limit = $this->intArgument($args, 'limit', 10, 1, static::LIMIT_MAX);

        $rows = $this->lines($from, $to)
            ->leftJoin('catalog_product as p', 'cop.product_uuid', '=', 'p.uuid')
            ->selectRaw('cop.product_uuid, MAX(p.title) as title, MAX(p.address) as address, SUM(cop.count) as quantity, COUNT(DISTINCT co.uuid) as orders, SUM(' . $this->lineSum() . ') as revenue')
            ->groupBy('cop.product_uuid')
            ->orderByDesc($sort)
            ->limit($limit)
            ->get();

        return [
            'period' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'sort' => $sort,
            'items' => $rows->map(fn ($row) => [
                'uuid' => (string) $row->product_uuid,
                'title' => $row->title ?? 'deleted product',
                'address' => $row->address,
                'quantity' => round((float) $row->quantity, 2),
                'orders' => (int) $row->orders,
                'revenue' => round((float) $row->revenue, 2),
            ])->values()->all(),
        ];
    }
}
