<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Models\CatalogCategory;
use App\Domain\Service\Catalog\CategoryService;

class CatalogCategoryListTool extends AbstractMcpTool
{
    public const NAME = 'catalog_category_list';
    public const TITLE = 'Catalog categories';
    public const DESCRIPTION = 'Returns the list of catalog categories (uuid, title, address, parent). Use it to find category_uuid for product search.';
    public const SCOPE = 'catalog/category';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'parent_uuid' => [
                    'type' => 'string',
                    'description' => 'Return only nested categories of this parent category',
                ],
            ],
        ];
    }

    public function execute(array $args = []): mixed
    {
        /** @var CategoryService $categoryService */
        $categoryService = $this->container->get(CategoryService::class);

        $categories = $categoryService->read(array_filter([
            'parent_uuid' => $args['parent_uuid'] ?? null,
            'status' => CatalogStatus::WORK,
            'order' => ['order' => 'asc', 'title' => 'asc'],
        ], fn ($value) => $value !== null));

        return $categories
            ->map(fn (CatalogCategory $category) => [
                'uuid' => (string) $category->uuid,
                'parent_uuid' => $category->parent_uuid ? (string) $category->parent_uuid : null,
                'title' => $category->title,
                'address' => $category->address,
                'is_hidden' => $category->is_hidden,
            ])
            ->values()
            ->all();
    }
}
