<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Models\CatalogProduct;

class CatalogProductSearchTool extends AbstractMcpTool
{
    public const NAME = 'catalog_product_search';
    public const TITLE = 'Search products';
    public const DESCRIPTION = 'Searches catalog products by text (title, vendor code, barcode) and/or category. Returns short product cards; use catalog_product_get for full details.';
    public const SCOPE = 'catalog/product';

    private const LIMIT_DEFAULT = 20;
    private const LIMIT_MAX = 100;

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Text to search in title, vendor code or barcode',
                ],
                'category_uuid' => [
                    'type' => 'string',
                    'description' => 'Filter by category uuid (see catalog_category_list)',
                ],
                'special' => [
                    'type' => 'boolean',
                    'description' => 'Only special offers',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::LIMIT_MAX,
                    'default' => self::LIMIT_DEFAULT,
                ],
                'offset' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'default' => 0,
                ],
            ],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $query = CatalogProduct::query()->where('status', CatalogStatus::WORK);

        if (!blank($text = trim((string) ($args['query'] ?? '')))) {
            $query->where(function ($query) use ($text): void {
                $query
                    ->where('title', 'like', '%' . $text . '%')
                    ->orWhere('vendorcode', $text)
                    ->orWhere('barcode', $text);
            });
        }
        if (!blank($args['category_uuid'] ?? null)) {
            $query->where('category_uuid', $args['category_uuid']);
        }
        if (isset($args['special'])) {
            $query->where('special', (bool) $args['special']);
        }

        $total = $query->count();
        $limit = min(self::LIMIT_MAX, max(1, (int) ($args['limit'] ?? self::LIMIT_DEFAULT)));
        $offset = max(0, (int) ($args['offset'] ?? 0));

        $products = $query
            ->orderBy('order')
            ->orderBy('title')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'items' => $products
                ->map(fn (CatalogProduct $product) => [
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
                ])
                ->values()
                ->all(),
        ];
    }
}
