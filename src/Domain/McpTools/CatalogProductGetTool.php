<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Service\Catalog\ProductService;

class CatalogProductGetTool extends AbstractMcpTool
{
    public const NAME = 'catalog_product_get';
    public const TITLE = 'Get product';
    public const DESCRIPTION = 'Returns full product details (description, prices, attributes, files, related products) by uuid, address, vendor code or barcode. With an API key hidden products are found too (their "status" is "delete").';
    public const SCOPE = 'catalog/product';

    private const KEYS = ['uuid', 'address', 'vendorcode', 'barcode'];

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uuid' => ['type' => 'string'],
                'address' => ['type' => 'string'],
                'vendorcode' => ['type' => 'string'],
                'barcode' => ['type' => 'string'],
            ],
            'description' => 'Pass one of the fields',
        ];
    }

    public function execute(array $args = []): mixed
    {
        $criteria = array_filter(array_intersect_key($args, array_flip(self::KEYS)), fn ($value) => is_string($value) && !blank($value));

        if (!$criteria) {
            throw new \InvalidArgumentException('One of fields is required: ' . implode(', ', self::KEYS));
        }

        /** @var ProductService $productService */
        $productService = $this->container->get(ProductService::class);

        /** @var \App\Domain\Models\CatalogProduct $product */
        // a caller with a key sees hidden products too (the operator checks what was created and shows it), the public one only visible
        $product = $productService->read(array_merge($criteria, ['status' => $this->apiKey === null ? CatalogStatus::WORK : CatalogStatus::LIST]));

        // the first price is hidden in the model (it is not for the site), the operator sees it
        return $product->makeVisible('priceFirst');
    }
}
