<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\McpTools\Concerns\BuildsAddress;
use App\Domain\McpTools\Concerns\SanitizesHtml;
use App\Domain\Models\CatalogCategory;
use App\Domain\Service\Catalog\CategoryService;

class CatalogCategoryCreateTool extends AbstractMcpTool
{
    use BuildsAddress;
    use SanitizesHtml;

    public const NAME = 'catalog_category_create';
    public const TITLE = 'Create category';
    public const DESCRIPTION = 'Adds a catalog category, optionally inside another one (parent_uuid). It is created HIDDEN from the menus unless hidden=false: the page works by its link, products can be put into it, '
        . 'the operator shows it in menus later (catalog_category_update is_hidden=false). The address is made of the parent address and the title unless "address" is passed.';
    public const READ_ONLY = false;
    public const SCOPE = 'catalog/category';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'title' => $string,
                'parent_uuid' => $string + ['description' => 'Parent category, empty - top level'],
                'description' => ['type' => 'string', 'description' => 'HTML (scripts and event handlers are removed)'],
                'hidden' => ['type' => 'boolean', 'default' => true, 'description' => 'false - show in the menus at once'],
                'meta_title' => $string,
                'meta_description' => $string,
                'meta_keywords' => $string,
                'address' => $string + ['description' => 'Page address, latin letters, digits and dashes; made of the title by default'],
            ],
            'required' => ['title'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $title = trim((string) ($args['title'] ?? ''));

        if ($title === '') {
            throw new \InvalidArgumentException('Field title is required');
        }

        $parent = null;

        if (!blank($args['parent_uuid'] ?? null)) {
            $parent = \Ramsey\Uuid\Uuid::isValid((string) $args['parent_uuid'])
                ? CatalogCategory::query()->where('uuid', $args['parent_uuid'])->where('status', CatalogStatus::WORK)->first()
                : null;

            if ($parent === null) {
                throw new \InvalidArgumentException('Parent category not found, see catalog_category_list');
            }
        }

        $data = [
            'title' => $title,
            'parent_uuid' => $parent ? (string) $parent->uuid : null,
            'address' => $this->buildAddress($parent ? (string) $parent->address : '', $title, $args['address'] ?? null),
            'is_hidden' => ($args['hidden'] ?? true) !== false,
            'status' => CatalogStatus::WORK,
            'export' => 'manual',
        ];

        if (array_key_exists('description', $args)) {
            $data['description'] = $this->sanitizeHtml((string) $args['description']);
        }

        $meta = array_filter([
            'title' => $args['meta_title'] ?? null,
            'description' => $args['meta_description'] ?? null,
            'keywords' => $args['meta_keywords'] ?? null,
        ], fn ($value) => $value !== null);

        if ($meta) {
            $data['meta'] = array_map('trim', $meta);
        }

        $category = $this->container->get(CategoryService::class)->create($data);

        $this->container->get(PubSub::class)->publish('api:catalog:category:create', $category);
        $this->logger->notice('Category created via MCP', ['uuid' => (string) $category->uuid]);

        return [
            'uuid' => (string) $category->uuid,
            'title' => $category->title,
            'address' => $category->address,
            'parent' => $parent?->title,
            'is_hidden' => (bool) $category->is_hidden,
        ];
    }
}
