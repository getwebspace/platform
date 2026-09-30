<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Service\Catalog\OrderService;

class CatalogOrderGetTool extends AbstractMcpTool
{
    public const NAME = 'catalog_order_get';
    public const TITLE = 'Get order';
    public const DESCRIPTION = 'Returns order details (status, customer contacts, delivery, products) by order serial number or uuid.';
    public const SCOPE = 'catalog/order';
    public const PRIVATE = true;

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'serial' => [
                    'type' => 'string',
                    'description' => 'Order number',
                ],
                'uuid' => ['type' => 'string'],
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

        return $orderService->read($criteria);
    }
}
