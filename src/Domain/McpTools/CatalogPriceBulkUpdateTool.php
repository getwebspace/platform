<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogProduct;
use App\Domain\Service\Catalog\ProductService;

class CatalogPriceBulkUpdateTool extends AbstractMcpTool
{
    public const NAME = 'catalog_price_bulk_update';
    public const TITLE = 'Change prices in bulk';
    public const DESCRIPTION = 'Changes prices of many products at once: by percent or by a fixed amount, for a category (optionally with nested ones) and/or by a title search. '
        . 'It is a PREVIEW by default: nothing changes, you get the number of products and examples of old / new price. '
        . 'To apply, show the preview to the operator, get explicit confirmation and call again with the same filters, apply=true and expected_count equal to the previewed total (the call is refused if the count differs). '
        . 'Only visible products are changed; the price never goes below zero.';
    public const READ_ONLY = false;
    public const SCOPE = 'catalog/product';

    private const FIELDS = ['price', 'priceWholesale'];

    private const MAX_PRODUCTS = 500;

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'category_uuid' => ['type' => 'string', 'description' => 'Only products of this category'],
                'include_nested' => ['type' => 'boolean', 'default' => true, 'description' => 'Include nested categories'],
                'search' => ['type' => 'string', 'description' => 'Only products with this text in the title'],
                'field' => ['type' => 'string', 'enum' => self::FIELDS, 'default' => 'price', 'description' => 'price - retail, priceWholesale - wholesale'],
                'percent' => ['type' => 'number', 'description' => 'Change by percent: 10 = +10%, -5 = -5%'],
                'amount' => ['type' => 'number', 'description' => 'Change by a fixed amount: 100 = +100, -50 = -50'],
                'apply' => ['type' => 'boolean', 'default' => false, 'description' => 'false - preview only'],
                'expected_count' => ['type' => 'integer', 'description' => 'Required with apply=true: the total from the preview'],
            ],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $field = $args['field'] ?? 'price';

        if (!in_array($field, self::FIELDS, true)) {
            throw new \InvalidArgumentException('Field "field" must be one of: ' . implode(', ', self::FIELDS));
        }
        if (isset($args['percent']) === isset($args['amount'])) {
            throw new \InvalidArgumentException('Pass exactly one of percent or amount');
        }

        $change = $args['percent'] ?? $args['amount'];

        if (!is_numeric($change) || (float) $change === 0.0) {
            throw new \InvalidArgumentException('The change must be a number, not zero');
        }
        if (isset($args['percent']) && $args['percent'] <= -100) {
            throw new \InvalidArgumentException('Percent must be greater than -100');
        }
        if (blank($args['category_uuid'] ?? null) && blank(trim((string) ($args['search'] ?? '')))) {
            throw new \InvalidArgumentException('Narrow the products down: pass category_uuid and/or search (all prices of the shop at once are not changed here)');
        }

        /** @var ProductService $productService */
        $productService = $this->container->get(ProductService::class);

        $filter = ['status' => CatalogStatus::WORK];

        if (!blank($args['category_uuid'] ?? null)) {
            $filter['category_uuid'] = $this->categories((string) $args['category_uuid'], $args['include_nested'] ?? true);
        }
        if (!blank($search = trim((string) ($args['search'] ?? '')))) {
            // case-insensitive for any language, unlike a raw LIKE in SQLite
            $filter['search'] = $search;
        }

        // one more than the limit is enough to know there are too many
        $products = $productService->read($filter + ['order' => ['title' => 'asc'], 'limit' => self::MAX_PRODUCTS + 1]);
        $total = $products->count();

        if ($total === 0) {
            throw new \DomainException('No products match the filters');
        }
        if ($total > self::MAX_PRODUCTS) {
            throw new \DomainException('More than ' . self::MAX_PRODUCTS . ' products match, at most ' . self::MAX_PRODUCTS . ' at once - narrow the filters');
        }

        $new = fn (CatalogProduct $product) => $this->recalculate((float) $product->{$field}, isset($args['percent']), (float) $change);

        $summary = [
            'field' => $field,
            'change' => isset($args['percent']) ? "{$change}%" : (string) $change,
            'total' => $total,
            'examples' => $products->take(10)->map(fn (CatalogProduct $product) => [
                'uuid' => (string) $product->uuid,
                'title' => $product->title,
                'old' => (float) $product->{$field},
                'new' => $new($product),
            ])->values()->all(),
        ];

        if (empty($args['apply'])) {
            return $summary + ['applied' => false, 'note' => 'Preview only. To apply: same arguments + apply=true + expected_count=' . $total];
        }
        if (($args['expected_count'] ?? null) !== $total) {
            throw new \DomainException("expected_count must be {$total} (the number of matching products now), got " . json_encode($args['expected_count'] ?? null));
        }

        $pubSub = $this->container->get(PubSub::class);

        foreach ($products as $product) {
            // keep the address: generation on update would move the page to another URL
            $product = $productService->update($product, [$field => $new($product), 'address' => $product->address]);

            $pubSub->publish('api:catalog:product:edit', $product);
        }

        $this->logger->notice('Prices changed in bulk via MCP', ['field' => $field, 'change' => $change, 'percent' => isset($args['percent']), 'count' => $total]);

        return $summary + ['applied' => true];
    }

    private function recalculate(float $price, bool $percent, float $change): float
    {
        return max(0, round($percent ? $price * (1 + $change / 100) : $price + $change, 2));
    }

    /**
     * @return array<int, string> uuids of the category and, optionally, all its descendants
     */
    private function categories(string $uuid, bool $nested): array
    {
        if (!\Ramsey\Uuid\Uuid::isValid($uuid) || CatalogCategory::query()->where('uuid', $uuid)->doesntExist()) {
            throw new \InvalidArgumentException('Category not found, see catalog_category_list');
        }

        $found = [$uuid];

        for ($level = $nested ? [$uuid] : []; $level;) {
            $level = CatalogCategory::query()->whereIn('parent_uuid', $level)->pluck('uuid')->map(fn ($item) => (string) $item)->diff($found)->values()->all();
            $found = [...$found, ...$level];
        }

        return $found;
    }
}
