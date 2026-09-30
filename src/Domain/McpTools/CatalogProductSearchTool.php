<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Models\CatalogProduct;
use App\Domain\Service\Catalog\ProductService;

class CatalogProductSearchTool extends AbstractMcpTool
{
    public const NAME = 'catalog_product_search';
    public const TITLE = 'Search products';
    public const DESCRIPTION = 'Searches catalog products by title (case-insensitive substring), vendor code or barcode, and/or category. Returns short product cards; use catalog_product_get for full details.';
    public const SCOPE = 'catalog/product';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Part of the product title',
                ],
                'vendorcode' => [
                    'type' => 'string',
                    'description' => 'Exact vendor code',
                ],
                'barcode' => [
                    'type' => 'string',
                    'description' => 'Exact barcode',
                ],
                'category_uuid' => [
                    'type' => 'string',
                    'description' => 'Filter by category uuid (see catalog_category_list)',
                ],
                'special' => [
                    'type' => 'boolean',
                    'description' => 'Only special offers',
                ],
            ] + $this->paginationSchema(),
        ];
    }

    public function execute(array $args = []): mixed
    {
        /** @var ProductService $productService */
        $productService = $this->container->get(ProductService::class);

        $filter = ['status' => CatalogStatus::WORK];

        if (!blank($query = trim((string) ($args['query'] ?? '')))) {
            $filter['search'] = $query;
        }
        // array form: a list, not a single-product lookup that throws on a miss
        foreach (['vendorcode', 'barcode', 'category_uuid'] as $key) {
            if (!blank($args[$key] ?? null)) {
                $filter[$key] = [(string) $args[$key]];
            }
        }
        if (isset($args['special'])) {
            $filter['special'] = (bool) $args['special'];
        }

        return ['total' => $productService->count($filter)] + $this->paginate(
            $args,
            fn (int $limit, int $offset) => $productService->read($filter + [
                'order' => ['order' => 'asc', 'title' => 'asc'],
                'limit' => $limit,
                'offset' => $offset,
            ]),
            fn (CatalogProduct $product) => [
                'uuid' => (string) $product->uuid,
                'title' => $product->title,
                'address' => $product->address,
                'category_uuid' => (string) $product->category_uuid,
                'vendorcode' => $product->vendorcode,
                'barcode' => $product->barcode,
                'price' => $product->price('price', 2),
                'price_wholesale' => $product->price('price_wholesale', 2),
                'quantity' => $product->quantity,
                'stock' => $product->stock,
                'special' => $product->special,
            ]
        );
    }
}
