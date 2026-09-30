<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Models\CatalogOrder;
use App\Domain\Models\Reference;
use App\Domain\Service\Catalog\OrderService;
use App\Domain\Service\Reference\ReferenceService;

class CatalogOrderListTool extends AbstractMcpTool
{
    public const NAME = 'catalog_order_list';
    public const TITLE = 'Orders';
    public const DESCRIPTION = 'Lists orders, newest first, optionally for a period (date_from / date_to). "new_only" - orders not handled yet (no status or the first order status). '
        . 'Filter by status title (see reference_list type=order_status) or search by order number, phone or e-mail. Use catalog_order_get for the full order.';
    public const PRIVATE = true;
    public const SCOPE = 'catalog/order';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'new_only' => ['type' => 'boolean', 'default' => false],
                'status' => ['type' => 'string', 'description' => 'Order status title or uuid'],
                'search' => ['type' => 'string', 'description' => 'Part of order number, phone or e-mail'],
                'date_from' => ['type' => 'string', 'description' => 'Orders created from this date, YYYY-MM-DD (whole day) or YYYY-MM-DD HH:MM'],
                'date_to' => ['type' => 'string', 'description' => 'Orders created up to this date, inclusive, same format'],
            ] + $this->paginationSchema(),
        ];
    }

    public function execute(array $args = []): mixed
    {
        $filter = [];

        if (!empty($args['new_only'])) {
            // same rule as the admin main page: no status yet or still the first one
            $first = $this->firstStatus();
            $filter['status_uuid'] = $first ? [null, (string) $first->uuid] : [null];
        } elseif (!blank($args['status'] ?? null)) {
            $filter['status_uuid'] = [(string) $this->findStatus((string) $args['status'])->uuid];
        }
        if (!blank($search = trim((string) ($args['search'] ?? '')))) {
            $filter['search'] = $search;
        }

        foreach (['date_from', 'date_to'] as $key) {
            if (!blank($args[$key] ?? null)) {
                $filter[$key] = $this->date((string) $args[$key], $key);
            }
        }
        if (isset($filter['date_from'], $filter['date_to']) && datetime($filter['date_from']) > datetime($filter['date_to'])) {
            throw new \InvalidArgumentException('date_from is later than date_to');
        }

        /** @var OrderService $orderService */
        $orderService = $this->container->get(OrderService::class);

        return $this->paginate(
            $args,
            fn (int $limit, int $offset) => $orderService->read($filter + [
                'order' => ['date' => 'desc'],
                'limit' => $limit,
                'offset' => $offset,
            ]),
            fn (CatalogOrder $order) => [
                'uuid' => (string) $order->uuid,
                'serial' => $order->serial,
                'date' => $order->date?->format('Y-m-d H:i'),
                'status' => $order->status?->title,
                'payment' => $order->payment?->title,
                'client' => $order->delivery['client'] ?? '',
                'phone' => $order->phone,
                'email' => $order->email,
                'items' => $order->products->count(),
                'total' => round($order->totalSum(), 2),
                'comment' => str_truncate((string) $order->comment, 100),
            ]
        );
    }

    private function date(string $value, string $field): string
    {
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value) !== 1 || strtotime($value) === false) {
            throw new \InvalidArgumentException("Field {$field} must be YYYY-MM-DD or YYYY-MM-DD HH:MM");
        }

        return $value;
    }

    private function statuses(): \Illuminate\Support\Collection
    {
        return $this->container->get(ReferenceService::class)->read([
            'type' => ReferenceType::ORDER_STATUS,
            'status' => true,
            'order' => ['order' => 'asc'],
        ]);
    }

    private function firstStatus(): ?Reference
    {
        return $this->statuses()->first();
    }

    private function findStatus(string $status): Reference
    {
        $statuses = $this->statuses();
        $found = $statuses->first(fn (Reference $item) => (string) $item->uuid === $status || mb_strtolower($item->title) === mb_strtolower($status));

        if ($found === null) {
            throw new \InvalidArgumentException('Unknown order status, allowed: ' . $statuses->pluck('title')->implode(', '));
        }

        return $found;
    }
}
