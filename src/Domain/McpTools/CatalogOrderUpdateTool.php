<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Service\Catalog\OrderService;

class CatalogOrderUpdateTool extends AbstractMcpTool
{
    public const NAME = 'catalog_order_update';
    public const TITLE = 'Update order details';
    public const DESCRIPTION = 'Corrects the contacts of an order (phone, e-mail), the client name and delivery address, the shipping date and the comment. By serial number or uuid. '
        . 'The contents (products) and the status are not changed here - the status has catalog_order_update_status. Pass only the fields to change; use dry_run to show the result first.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const SCOPE = 'catalog/order';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'serial' => $string + ['description' => 'Order number'],
                'uuid' => $string,
                'phone' => $string,
                'email' => $string,
                'client' => $string + ['description' => 'Name of the client in the delivery data'],
                'address' => $string + ['description' => 'Delivery address'],
                'shipping' => $string + ['description' => 'Shipping date, YYYY-MM-DD or YYYY-MM-DD HH:MM; empty string clears it'],
                'comment' => $string,
            ],
            'description' => 'Pass serial or uuid',
        ];
    }

    public function execute(array $args = []): mixed
    {
        $criteria = array_filter(array_intersect_key($args, array_flip(['serial', 'uuid'])), fn ($value) => is_string($value) && !blank($value));

        if (!$criteria) {
            throw new \InvalidArgumentException('Field serial or uuid is required');
        }

        /** @var OrderService $orderService */
        $orderService = $this->container->get(OrderService::class);
        $order = $orderService->read($criteria);

        $data = [];

        foreach (['phone', 'comment'] as $key) {
            if (array_key_exists($key, $args)) {
                $data[$key] = trim((string) $args[$key]);
            }
        }
        if (array_key_exists('email', $args)) {
            $email = trim((string) $args['email']);

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException('Field email is not a valid e-mail address');
            }

            $data['email'] = $email;
        }

        $delivery = array_filter(['client' => $args['client'] ?? null, 'address' => $args['address'] ?? null], fn ($value) => $value !== null);

        if ($delivery) {
            $data['delivery'] = array_merge((array) $order->delivery, array_map(fn ($value) => trim((string) $value), $delivery));
        }
        if (array_key_exists('shipping', $args)) {
            $value = trim((string) $args['shipping']);

            if ($value !== '' && (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value) !== 1 || strtotime($value) === false)) {
                throw new \InvalidArgumentException('Field shipping must be YYYY-MM-DD or YYYY-MM-DD HH:MM');
            }

            $data['shipping'] = $value === '' ? null : datetime($value)->format('Y-m-d H:i:s');
        }

        if (!$data) {
            throw new \InvalidArgumentException('Nothing to change');
        }

        $order = $orderService->update($order, $data);

        $this->container->get(PubSub::class)->publish('api:catalog:order:edit', $order);
        $this->logger->notice('Order updated via MCP', ['order' => (string) $order->uuid, 'fields' => array_keys($data)]);

        return [
            'uuid' => (string) $order->uuid,
            'serial' => $order->serial,
            'status' => $order->status?->title,
            'phone' => $order->phone,
            'email' => $order->email,
            'client' => $order->delivery['client'] ?? '',
            'address' => $order->delivery['address'] ?? '',
            'shipping' => $order->shipping ? datetime($order->shipping)->format('Y-m-d H:i') : null,
            'comment' => $order->comment,
            'changed_fields' => array_keys($data),
        ];
    }
}
