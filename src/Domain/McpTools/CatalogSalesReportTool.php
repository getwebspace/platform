<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\McpTools\Concerns\FindsOrderStatus;
use App\Domain\McpTools\Concerns\ParsesPeriod;
use App\Domain\McpTools\Concerns\SalesQueries;
use App\Domain\Models\CatalogCategory;
use App\Domain\Models\Reference;
use App\Domain\Service\Reference\ReferenceService;

class CatalogSalesReportTool extends AbstractMcpTool
{
    use FindsOrderStatus;
    use ParsesPeriod;
    use SalesQueries;

    public const NAME = 'catalog_sales_report';
    public const TITLE = 'Sales report';
    public const DESCRIPTION = 'Orders, revenue and average check for a period (30 days by default), grouped by day, week, month, order status or product category. '
        . 'Money is counted by the order lines (price minus discount, tax if it is not included in the price). All orders are counted, also cancelled ones, unless "status" narrows it - the report by status shows which are which. '
        . 'Periods without orders are not listed.';
    public const PRIVATE = true;
    public const SCOPE = 'catalog/order';

    private const GROUPS = ['day', 'week', 'month', 'status', 'category'];

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $this->periodSchema() + [
                'group_by' => ['type' => 'string', 'enum' => self::GROUPS, 'default' => 'day'],
                'status' => ['type' => 'string', 'description' => 'Count only orders with this status (title or uuid)'],
            ],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $group = $args['group_by'] ?? 'day';

        if (!in_array($group, self::GROUPS, true)) {
            throw new \InvalidArgumentException('Field group_by must be one of: ' . implode(', ', self::GROUPS));
        }

        [$from, $to] = $this->period($args);
        $statuses = blank($args['status'] ?? null) ? [] : [(string) $this->findOrderStatus((string) $args['status'])->uuid];

        $rows = $group === 'category' ? $this->byCategory($from, $to, $statuses) : $this->byOrder($group, $from, $to, $statuses);

        $orders = array_sum(array_column($rows, 'orders'));
        $revenue = round(array_sum(array_column($rows, 'revenue')), 2);

        // a category row counts an order once per category: the total is of the orders, not of the rows
        if ($group === 'category') {
            $total = $this->lines($from, $to, $statuses)->selectRaw('COUNT(DISTINCT co.uuid) as orders, COALESCE(SUM(' . $this->lineSum() . '), 0) as revenue')->first();
            $orders = (int) $total->orders;
            $revenue = round((float) $total->revenue, 2);
        }

        return [
            'period' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'group_by' => $group,
            'totals' => ['orders' => $orders, 'revenue' => $revenue, 'average_check' => $orders > 0 ? round($revenue / $orders, 2) : 0],
            'rows' => $rows,
        ];
    }

    /**
     * Day / week / month / status: one query for the orders, grouped in PHP (date functions differ between databases)
     *
     * @param string[] $statuses
     */
    private function byOrder(string $group, \Carbon\Carbon $from, \Carbon\Carbon $to, array $statuses): array
    {
        $orders = $this->lines($from, $to, $statuses)
            ->selectRaw('co.uuid, co.date, co.status_uuid, SUM(' . $this->lineSum() . ') as total')
            ->groupBy('co.uuid', 'co.date', 'co.status_uuid')
            ->get();

        $titles = $group === 'status'
            ? $this->container->get(ReferenceService::class)->read(['type' => ReferenceType::ORDER_STATUS])->mapWithKeys(fn (Reference $status) => [(string) $status->uuid => $status->title])
            : collect();

        $buckets = [];

        foreach ($orders as $order) {
            $date = datetime($order->date);
            $key = match ($group) {
                'week' => $date->copy()->startOfWeek()->format('Y-m-d'),
                'month' => $date->format('Y-m'),
                'status' => $order->status_uuid ? (string) $order->status_uuid : '',
                default => $date->format('Y-m-d'),
            };
            $label = $group === 'status' ? ($titles->get($key) ?? ($key === '' ? 'no status' : 'unknown status')) : $key;

            $buckets[$key] ??= ['key' => $key, 'label' => $label, 'orders' => 0, 'revenue' => 0.0];
            ++$buckets[$key]['orders'];
            $buckets[$key]['revenue'] += (float) $order->total;
        }

        // periods in time order, statuses by revenue
        if ($group === 'status') {
            uasort($buckets, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        } else {
            ksort($buckets);
        }

        return $this->finish($buckets, $group === 'week' ? 'week starting' : null);
    }

    /**
     * @param string[] $statuses
     */
    private function byCategory(\Carbon\Carbon $from, \Carbon\Carbon $to, array $statuses): array
    {
        $rows = $this->lines($from, $to, $statuses)
            ->join('catalog_product as p', 'cop.product_uuid', '=', 'p.uuid')
            ->selectRaw('p.category_uuid, COUNT(DISTINCT co.uuid) as orders, SUM(' . $this->lineSum() . ') as revenue')
            ->groupBy('p.category_uuid')
            ->get();

        $titles = CatalogCategory::query()->whereIn('uuid', $rows->pluck('category_uuid')->all())->pluck('title', 'uuid');
        $buckets = [];

        foreach ($rows->sortByDesc('revenue') as $row) {
            $key = (string) $row->category_uuid;
            $buckets[$key] = ['key' => $key, 'label' => $titles->get($key) ?? 'unknown category', 'orders' => (int) $row->orders, 'revenue' => (float) $row->revenue];
        }

        return $this->finish($buckets);
    }

    private function finish(array $buckets, ?string $prefix = null): array
    {
        return array_values(array_map(fn (array $bucket) => [
            'key' => $bucket['key'],
            'label' => $prefix ? "{$prefix} {$bucket['label']}" : $bucket['label'],
            'orders' => $bucket['orders'],
            'revenue' => round($bucket['revenue'], 2),
            'average_check' => $bucket['orders'] > 0 ? round($bucket['revenue'] / $bucket['orders'], 2) : 0,
        ], $buckets));
    }
}
