<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Models\CatalogAttribute;
use App\Domain\Models\CatalogCategory;
use App\Domain\Service\Catalog\CategoryService;

class CatalogCategoryGetTool extends AbstractMcpTool
{
    public const NAME = 'catalog_category_get';
    public const TITLE = 'Get category';
    public const DESCRIPTION = 'All the fields of one catalog category: description, SEO, parent, products per page, sorting, templates, own data, ids of outside systems, '
        . 'its attributes and which of them are filters, the number of products and nested categories. By uuid or address. Read it before changing it with catalog_category_update.';
    public const PRIVATE = true;
    public const SCOPE = 'catalog/category';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uuid' => ['type' => 'string'],
                'address' => ['type' => 'string'],
            ],
            'description' => 'Pass uuid or address',
        ];
    }

    public function execute(array $args = []): mixed
    {
        $criteria = array_filter(array_intersect_key($args, array_flip(['uuid', 'address'])), fn ($value) => is_string($value) && !blank($value));

        if (!$criteria) {
            throw new \InvalidArgumentException('Field uuid or address is required');
        }

        // hidden and deleted ones are there for the operator (the public site does not show them)
        /** @var CatalogCategory $category */
        $category = $this->container->get(CategoryService::class)->read($criteria + ['parent_uuid' => '', 'status' => CatalogStatus::LIST]);
        $attributes = $category->getRelationValue('attributes');
        $parent = $category->parent_uuid ? CatalogCategory::query()->where('uuid', $category->parent_uuid)->first() : null;

        return [
            'uuid' => (string) $category->uuid,
            'title' => $category->title,
            'address' => $category->address,
            'url' => rtrim((string) $this->parameter('common_homepage', ''), '/') . '/catalog/' . ltrim((string) $category->address, '/'),
            'status' => $category->status,
            'is_hidden' => (bool) $category->is_hidden,
            'is_allow_nested' => (bool) $category->is_allow_nested,
            'parent' => $parent ? ['uuid' => (string) $parent->uuid, 'title' => $parent->title, 'address' => $parent->address] : null,
            'description' => $category->description,
            'meta' => $category->meta,
            'pagination' => $category->pagination,
            'sort' => $category->sort,
            'template' => $category->template,
            'order' => $category->order,
            'specifics' => $category->specifics,
            'external_id' => $category->external_id,
            'export' => $category->export,
            'system' => $category->system,
            'attributes' => $attributes->map(fn (CatalogAttribute $attribute) => [
                'uuid' => (string) $attribute->uuid,
                'address' => $attribute->address,
                'title' => $attribute->title,
                'type' => $attribute->type,
                'is_filter' => (bool) $attribute->is_filter,
            ])->values()->all(),
            'filters' => $attributes->filter(fn (CatalogAttribute $attribute) => $attribute->is_filter)->pluck('address')->values()->all(),
            'products' => $category->products()->count(),
            'nested' => CatalogCategory::query()->where('parent_uuid', (string) $category->uuid)->count(),
        ];
    }
}
