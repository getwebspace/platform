<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\McpTools\Concerns\ReadsCategoryFields;
use App\Domain\Models\CatalogCategory;
use App\Domain\Service\Catalog\CategoryService;

class CatalogCategoryUpdateTool extends AbstractMcpTool
{
    use ReadsCategoryFields;

    public const NAME = 'catalog_category_update';
    public const TITLE = 'Update category';
    public const DESCRIPTION = 'Changes a catalog category (uuid from catalog_category_list): any of its fields - title, description, SEO, visibility, products per page, sorting, templates, order, own data, ids of outside systems - '
        . 'and its attributes (characteristics and filters: attributes_add / attributes_remove), and moves it into another category (parent_uuid, "" - to the top level). Pass only the fields to change. '
        . 'The page address is kept unless "address" is passed. is_hidden hides the category from menus, status=delete hides it from the site (nothing is deleted).';
    public const READ_ONLY = false;
    public const SCOPE = 'catalog/category';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'uuid' => $string,
                'is_hidden' => ['type' => 'boolean', 'description' => 'Hide from menus, the page stays reachable by the link'],
                'status' => ['type' => 'string', 'enum' => CatalogStatus::LIST, 'description' => 'work - visible, delete - hidden'],
                'parent_uuid' => $string + ['description' => 'Move into this category; "" - to the top level'],
                'address' => $string + ['description' => 'Change the page address (breaks existing links!)'],
            ] + $this->categoryFieldsSchema(),
            'required' => ['uuid'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        if (!is_string($args['uuid'] ?? null) || !\Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
            throw new \InvalidArgumentException('Field uuid is required');
        }

        /** @var CategoryService $categoryService */
        $categoryService = $this->container->get(CategoryService::class);
        /** @var CatalogCategory $category */
        $category = $categoryService->read(['uuid' => $args['uuid'], 'status' => CatalogStatus::LIST]);

        $data = $this->categoryData($args, $category);

        if (array_key_exists('is_hidden', $args)) {
            $data['is_hidden'] = (bool) $args['is_hidden'];
        }
        if (array_key_exists('status', $args)) {
            if (!in_array($args['status'], CatalogStatus::LIST, true)) {
                throw new \InvalidArgumentException('Field status must be one of: ' . implode(', ', CatalogStatus::LIST));
            }

            $data['status'] = $args['status'];
        }
        if (array_key_exists('parent_uuid', $args)) {
            $parent = $this->checkParent($args['parent_uuid'], $category);
            $data['parent_uuid'] = $parent ? (string) $parent->uuid : null;
        }
        if (!blank($args['address'] ?? null)) {
            $data['address'] = trim((string) $args['address']);
        }

        if (!$data) {
            throw new \InvalidArgumentException('Nothing to change');
        }

        // address generation on update would move the page to another URL
        $update = $data + ['address' => $category->address];

        $category = $categoryService->update($category, $update);

        $this->container->get(PubSub::class)->publish('api:catalog:category:edit', $category);
        $this->logger->notice('Category updated via MCP', ['uuid' => (string) $category->uuid, 'fields' => array_keys($data)]);

        return [
            'uuid' => (string) $category->uuid,
            'title' => $category->title,
            'address' => $category->address,
            'is_hidden' => $category->is_hidden,
            'status' => $category->status,
            'changed_fields' => array_keys($data),
        ];
    }
}
