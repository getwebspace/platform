<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Models\CatalogProduct;
use Illuminate\Database\Eloquent\Builder;

class CatalogStockReportTool extends AbstractMcpTool
{
    public const NAME = 'catalog_stock_report';
    public const TITLE = 'Catalog problems report';
    public const DESCRIPTION = 'Visible products that need attention: out of stock, stock below the minimum order, no price, no image, no description. For every check: how many and examples (title, price, stock). '
        . 'Note: stock 0 may be normal for services or products without stock accounting - the operator decides. Fix with catalog_product_update.';
    public const PRIVATE = true;
    public const SCOPE = 'catalog/product';

    private const CHECKS = ['out_of_stock', 'low_stock', 'no_price', 'no_image', 'no_description'];

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'checks' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => self::CHECKS], 'description' => 'Which checks to run, all by default'],
                'category_uuid' => ['type' => 'string', 'description' => 'Only products of this category'],
                'examples' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 50, 'default' => 10, 'description' => 'How many examples per check'],
            ],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $checks = $args['checks'] ?? self::CHECKS;

        if (!is_array($checks) || array_diff($checks, self::CHECKS) !== []) {
            throw new \InvalidArgumentException('Field checks must be a list of: ' . implode(', ', self::CHECKS));
        }

        $examples = $this->intArgument($args, 'examples', 10, 0, 50);

        $base = fn (): Builder => CatalogProduct::query()
            ->where('status', CatalogStatus::WORK)
            ->when(!blank($args['category_uuid'] ?? null), fn (Builder $query) => $query->where('category_uuid', (string) $args['category_uuid']));

        $conditions = [
            'out_of_stock' => fn (Builder $query) => $query->where('stock', '<=', 0),
            'low_stock' => fn (Builder $query) => $query->where('stock', '>', 0)->whereColumn('stock', '<', 'quantityMin'),
            'no_price' => fn (Builder $query) => $query->where('price', '<=', 0),
            'no_image' => fn (Builder $query) => $query->whereDoesntHave('images'),
            'no_description' => fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereNull('description')->orWhere('description', '')),
        ];

        $result = [];

        foreach (array_values(array_unique($checks)) as $check) {
            $query = $conditions[$check]($base());

            $cards = [];

            if ($examples > 0) {
                /** @var CatalogProduct $product */
                foreach ($query->orderBy('title')->limit($examples)->get() as $product) {
                    $cards[] = ['uuid' => (string) $product->uuid, 'title' => $product->title, 'price' => $product->price, 'stock' => $product->stock];
                }
            }

            $result[$check] = ['count' => (clone $query)->count(), 'examples' => $cards];
        }

        return ['visible_products' => $base()->count(), 'checks' => $result];
    }
}
