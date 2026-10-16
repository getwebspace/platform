<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\McpTools\Concerns\SanitizesHtml;
use App\Domain\Models\CatalogProduct;
use App\Domain\Service\Catalog\ProductService;

class CatalogProductUpdateTool extends AbstractMcpTool
{
    use SanitizesHtml;

    public const NAME = 'catalog_product_update';
    public const TITLE = 'Update product';
    public const DESCRIPTION = 'Changes fields of one catalog product (uuid from catalog_product_search). Pass only the fields to change: title, price, stock, descriptions (HTML), SEO, flags. '
        . 'The page address is kept unless "address" is passed explicitly. status=delete hides the product from the site (it is not deleted). '
        . 'Show the change to the operator and get confirmation first. For many products at once use catalog_price_bulk_update.';
    public const READ_ONLY = false;
    public const SCOPE = 'catalog/product';

    private const SCALARS = ['title', 'vendorcode', 'barcode', 'country', 'manufacturer'];

    private const NUMBERS = ['price', 'priceWholesale', 'discount', 'quantity', 'quantityMin', 'stock', 'tax'];

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];
        $number = ['type' => 'number', 'minimum' => 0];

        return [
            'type' => 'object',
            'properties' => [
                'uuid' => $string,
                'title' => $string,
                'description' => ['type' => 'string', 'description' => 'Main description, HTML (scripts and event handlers are removed)'],
                'extra' => ['type' => 'string', 'description' => 'Extra description, HTML'],
                'price' => $number + ['description' => 'Retail price'],
                'priceWholesale' => $number,
                'discount' => $number + ['description' => 'Discount amount from the price'],
                'quantity' => $number + ['description' => 'Quantity in a pack / minimal step'],
                'stock' => $number + ['description' => 'Stock left'],
                'special' => ['type' => 'boolean', 'description' => 'Special offer'],
                'status' => ['type' => 'string', 'enum' => CatalogStatus::LIST, 'description' => 'work - visible, delete - hidden'],
                'category_uuid' => $string + ['description' => 'Move to another category'],
                'vendorcode' => $string,
                'barcode' => $string,
                'country' => $string,
                'manufacturer' => $string,
                'meta_title' => $string,
                'meta_description' => $string,
                'meta_keywords' => $string,
                'address' => $string + ['description' => 'Change the page address (breaks existing links!)'],
            ],
            'required' => ['uuid'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        if (!is_string($args['uuid'] ?? null) || !\Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
            throw new \InvalidArgumentException('Field uuid is required');
        }

        /** @var ProductService $productService */
        $productService = $this->container->get(ProductService::class);
        /** @var CatalogProduct $product */
        $product = $productService->read(['uuid' => $args['uuid'], 'status' => CatalogStatus::LIST]);

        $data = [];

        foreach (self::SCALARS as $key) {
            if (array_key_exists($key, $args)) {
                $data[$key] = trim((string) $args[$key]);
            }
        }
        if (array_key_exists('title', $data) && $data['title'] === '') {
            throw new \InvalidArgumentException('Field title cannot be empty');
        }
        foreach (self::NUMBERS as $key) {
            if (array_key_exists($key, $args)) {
                if (!is_numeric($args[$key]) || $args[$key] < 0) {
                    throw new \InvalidArgumentException("Field {$key} must be a number, zero or more");
                }

                $data[$key] = round((float) $args[$key], 2);
            }
        }
        foreach (['description', 'extra'] as $key) {
            if (array_key_exists($key, $args)) {
                $data[$key] = $this->sanitizeHtml((string) $args[$key]);
            }
        }
        if (array_key_exists('special', $args)) {
            $data['special'] = (bool) $args['special'];
        }
        if (array_key_exists('status', $args)) {
            if (!in_array($args['status'], CatalogStatus::LIST, true)) {
                throw new \InvalidArgumentException('Field status must be one of: ' . implode(', ', CatalogStatus::LIST));
            }

            $data['status'] = $args['status'];
        }
        if (!blank($args['category_uuid'] ?? null)) {
            $category = \App\Domain\Models\CatalogCategory::query()->where('uuid', $args['category_uuid'])->first();

            if ($category === null) {
                throw new \InvalidArgumentException('Category not found, see catalog_category_list');
            }

            $data['category_uuid'] = (string) $category->uuid;
        }

        $meta = array_filter([
            'title' => $args['meta_title'] ?? null,
            'description' => $args['meta_description'] ?? null,
            'keywords' => $args['meta_keywords'] ?? null,
        ], fn ($value) => $value !== null);

        if ($meta) {
            $data['meta'] = array_merge((array) $product->meta, array_map('trim', $meta));
        }

        // address generation on update would move the page to another URL
        $data['address'] = blank($args['address'] ?? null) ? $product->address : trim((string) $args['address']);

        if (count($data) === 1) {
            throw new \InvalidArgumentException('Nothing to change');
        }

        $addressBefore = $product->address;
        $before = ['title' => $product->title, 'price' => $product->price, 'stock' => $product->stock, 'status' => $product->status];

        $product = $productService->update($product, $data);

        $this->container->get(PubSub::class)->publish('api:catalog:product:edit', $product);
        $this->logger->notice('Product updated via MCP', ['uuid' => (string) $product->uuid, 'fields' => array_keys($data)]);

        return [
            'uuid' => (string) $product->uuid,
            'title' => $product->title,
            'address' => $product->address,
            'status' => $product->status,
            'price' => $product->price,
            'stock' => $product->stock,
            'changed_fields' => array_keys(array_diff_key($data, $data['address'] === $addressBefore ? ['address' => 1] : [])),
            'before' => $before,
        ];
    }
}
