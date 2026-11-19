<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\McpTools\Concerns\FindsOrderStatus;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\Reference;
use App\Domain\Models\User;
use App\Domain\Service\Catalog\OrderService;

class CatalogOrderCreateTool extends AbstractMcpTool
{
    use FindsOrderStatus;

    public const NAME = 'catalog_order_create';
    public const TITLE = 'Create order';
    public const DESCRIPTION = 'Creates an order taken outside the site (phone call, messenger): customer contacts (phone or e-mail) and items. Prices are always the catalog prices of the moment - they can not be set here. '
        . 'The customer and the staff are NOT notified (no letters). Status is the first order status unless "status" is passed (see reference_list type=order_status). Check the items and the total with the operator before creating. Use dry_run to preview the total.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const SCOPE = 'catalog/order';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 100,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'product_uuid' => $string,
                            'count' => ['type' => 'number', 'description' => 'Quantity, more than 0'],
                        ],
                        'required' => ['product_uuid', 'count'],
                    ],
                ],
                'phone' => $string,
                'email' => $string,
                'client' => $string + ['description' => 'Customer name'],
                'address' => $string + ['description' => 'Delivery address'],
                'comment' => $string,
                'status' => $string + ['description' => 'Order status title or uuid'],
                'payment' => $string + ['description' => 'Payment method title or uuid (reference_list type=payment)'],
                'user_uuid' => $string + ['description' => 'Registered customer uuid (user_list), optional'],
            ],
            'required' => ['items'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $phone = trim((string) ($args['phone'] ?? ''));
        $email = trim((string) ($args['email'] ?? ''));

        if ($phone === '' && $email === '') {
            throw new \InvalidArgumentException('Pass phone or email of the customer');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Field email is not a valid e-mail address');
        }
        if (!is_array($args['items'] ?? null) || !$args['items'] || count($args['items']) > 100) {
            throw new \InvalidArgumentException('Field items must hold from 1 to 100 products');
        }

        $products = [];

        foreach ($args['items'] as $item) {
            $uuid = is_array($item) ? ($item['product_uuid'] ?? null) : null;
            $count = is_array($item) ? ($item['count'] ?? null) : null;

            if (!is_string($uuid) || !\Ramsey\Uuid\Uuid::isValid($uuid) || !CatalogProduct::query()->where('uuid', $uuid)->exists()) {
                throw new \InvalidArgumentException('Product not found: ' . (is_string($uuid) ? $uuid : 'no product_uuid'));
            }
            if (!is_numeric($count) || $count <= 0 || $count > 100000) {
                throw new \InvalidArgumentException("Field count of product {$uuid} must be more than 0");
            }
            if (isset($products[$uuid])) {
                throw new \InvalidArgumentException("Product {$uuid} is listed twice, sum the quantity");
            }

            // the price type is fixed: the catalog price, whatever the caller sends
            $products[$uuid] = ['count' => (float) $count, 'price_type' => 'price'];
        }

        $status = blank($args['status'] ?? null) ? $this->firstOrderStatus() : $this->findOrderStatus((string) $args['status']);
        $payment = null;

        if (!blank($args['payment'] ?? null)) {
            $needle = mb_strtolower(trim((string) $args['payment']));
            $methods = Reference::query()->where('type', ReferenceType::PAYMENT)->where('status', true)->get();
            $payment = $methods->first(fn (Reference $item) => (string) $item->uuid === trim((string) $args['payment']) || mb_strtolower($item->title) === $needle);

            if ($payment === null) {
                throw new \InvalidArgumentException('Unknown payment method, allowed: ' . $methods->pluck('title')->implode(', '));
            }
        }
        if (!blank($args['user_uuid'] ?? null) && (!is_string($args['user_uuid']) || !User::query()->whereKey($args['user_uuid'])->exists())) {
            throw new \InvalidArgumentException('User not found');
        }

        /** @var OrderService $orderService */
        $orderService = $this->container->get(OrderService::class);
        $order = $orderService->create([
            'phone' => $phone,
            'email' => $email,
            'comment' => trim((string) ($args['comment'] ?? '')),
            'delivery' => array_map(fn ($value) => trim((string) $value), array_filter(['client' => $args['client'] ?? null, 'address' => $args['address'] ?? null], fn ($value) => $value !== null)),
            'status_uuid' => $status?->uuid,
            'payment_uuid' => $payment?->uuid,
            'user_uuid' => blank($args['user_uuid'] ?? null) ? null : $args['user_uuid'],
            'system' => 'mcp',
            'products' => $products,
        ]);

        // the create event mails the customer and the staff: not published, on purpose
        $this->logger->notice('Order created via MCP', ['order' => (string) $order->uuid, 'items' => count($products)]);

        $order->load('products');

        return [
            'uuid' => (string) $order->uuid,
            'serial' => $order->serial,
            'status' => $order->status?->title,
            'payment' => $order->payment?->title,
            'phone' => $order->phone,
            'email' => $order->email,
            'items' => $order->products->count(),
            'total' => round($order->totalSum(), 2),
            'notified' => false,
        ];
    }
}
