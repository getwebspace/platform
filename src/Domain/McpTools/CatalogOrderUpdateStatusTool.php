<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\McpTools\Concerns\FindsOrderStatus;
use App\Domain\Service\Catalog\OrderService;

class CatalogOrderUpdateStatusTool extends AbstractMcpTool
{
    use FindsOrderStatus;

    public const NAME = 'catalog_order_update_status';
    public const TITLE = 'Change order status';
    public const DESCRIPTION = 'Changes the status of one order (by serial number or uuid). The status is a title or uuid from reference_list type=order_status. '
        . 'Plugins subscribed to order changes (payments, notifications) react to it, so confirm the order and the new status with the operator first. Returns the old and the new status.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const SCOPE = 'catalog/order';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'serial' => ['type' => 'string', 'description' => 'Order number'],
                'uuid' => ['type' => 'string'],
                'status' => ['type' => 'string', 'description' => 'New status title or uuid'],
            ],
            'required' => ['status'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $criteria = array_filter(array_intersect_key($args, array_flip(['serial', 'uuid'])), fn ($value) => is_string($value) && !blank($value));

        if (!$criteria) {
            throw new \InvalidArgumentException('Field serial or uuid is required');
        }
        if (blank($args['status'] ?? null)) {
            throw new \InvalidArgumentException('Field status is required');
        }

        $status = $this->findOrderStatus((string) $args['status']);

        /** @var OrderService $orderService */
        $orderService = $this->container->get(OrderService::class);
        $order = $orderService->read($criteria);
        $before = $order->status?->title;

        if ((string) $order->status_uuid !== (string) $status->uuid) {
            $order = $orderService->update($order, ['status_uuid' => (string) $status->uuid]);

            $this->container->get(PubSub::class)->publish('api:catalog:order:edit', $order);
            $this->logger->notice('Order status changed via MCP', ['order' => (string) $order->uuid, 'status' => (string) $status->uuid]);
        }

        return [
            'uuid' => (string) $order->uuid,
            'serial' => $order->serial,
            'status_before' => $before,
            'status' => $status->title,
            'changed' => $before !== $status->title,
        ];
    }
}
