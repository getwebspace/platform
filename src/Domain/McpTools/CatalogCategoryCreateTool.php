<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\McpTools\Concerns\BuildsAddress;
use App\Domain\McpTools\Concerns\ReadsCategoryFields;
use App\Domain\Service\Catalog\CategoryService;

class CatalogCategoryCreateTool extends AbstractMcpTool
{
    use BuildsAddress;
    use ReadsCategoryFields;

    public const NAME = 'catalog_category_create';
    public const TITLE = 'Create category';
    public const DESCRIPTION = 'Adds a catalog category, optionally inside another one (parent_uuid), with any of its fields: description, SEO, products per page, sorting, templates, attributes (characteristics and filters), own data, ids of outside systems. '
        . 'It is created HIDDEN from the menus unless hidden=false: the page works by its link, products can be put into it, the operator shows it in menus later (catalog_category_update is_hidden=false). '
        . 'The address is made of the parent address and the title unless "address" is passed. A nested category gets the attributes of its parent.';
    public const READ_ONLY = false;
    public const SCOPE = 'catalog/category';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'parent_uuid' => $string + ['description' => 'Parent category, empty - top level'],
                'hidden' => ['type' => 'boolean', 'default' => true, 'description' => 'false - show in the menus at once'],
                'address' => $string + ['description' => 'Page address, latin letters, digits and dashes; made of the title by default'],
            ] + $this->categoryFieldsSchema(),
            'required' => ['title'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $data = $this->categoryData($args);

        if (!isset($data['title'])) {
            throw new \InvalidArgumentException('Field title is required');
        }

        $parent = $this->checkParent($args['parent_uuid'] ?? null, null);

        $data += [
            'parent_uuid' => $parent ? (string) $parent->uuid : null,
            'address' => $this->buildAddress($parent ? (string) $parent->address : '', $data['title'], $args['address'] ?? null),
            'is_hidden' => ($args['hidden'] ?? true) !== false,
            'status' => CatalogStatus::WORK,
        ];
        $data['export'] ??= 'manual';

        $category = $this->container->get(CategoryService::class)->create($data);

        $this->container->get(PubSub::class)->publish('api:catalog:category:create', $category);
        $this->logger->notice('Category created via MCP', ['uuid' => (string) $category->uuid]);

        return [
            'uuid' => (string) $category->uuid,
            'title' => $category->title,
            'address' => $category->address,
            'parent' => $parent?->title,
            'is_hidden' => (bool) $category->is_hidden,
            'attributes' => $category->getRelationValue('attributes')->pluck('address')->values()->all(),
        ];
    }
}
