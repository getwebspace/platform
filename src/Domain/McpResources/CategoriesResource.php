<?php declare(strict_types=1);

namespace App\Domain\McpResources;

use App\Domain\AbstractMcpResource;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Models\CatalogCategory;

class CategoriesResource extends AbstractMcpResource
{
    public const URI = 'platform://catalog/categories';
    public const NAME = 'catalog_categories';
    public const TITLE = 'Catalog structure';
    public const DESCRIPTION = 'The tree of catalog categories with uuid, title and address: use it to find a category without calling a tool.';
    public const SCOPE = 'catalog/category';
    public const PRIVATE = true;

    public function read(): string
    {
        // the whole structure is small, grouping in PHP keeps it simple
        $rows = CatalogCategory::all()
            ->where('status', CatalogStatus::WORK)
            ->sortBy([['order', 'asc'], ['title', 'asc']])
            ->groupBy(fn (CatalogCategory $category) => (string) $category->parent_uuid);

        return $this->json($this->branch($rows, ''));
    }

    /**
     * @param \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, CatalogCategory>> $rows categories grouped by parent
     *
     * @return array<int, array<string, mixed>>
     */
    private function branch(\Illuminate\Support\Collection $rows, string $parent): array
    {
        $result = [];

        /** @var CatalogCategory $category */
        foreach ($rows->get($parent, collect()) as $category) {
            $children = $this->branch($rows, (string) $category->uuid);

            $result[] = array_filter([
                'uuid' => (string) $category->uuid,
                'title' => $category->title,
                'address' => $category->address,
                'hidden' => $category->is_hidden ?: null,
                'children' => $children ?: null,
            ], fn ($value) => $value !== null);
        }

        return $result;
    }
}
