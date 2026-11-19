<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\McpTools\Concerns\BuildsAddress;
use App\Domain\Models\PublicationCategory;
use App\Domain\Service\Publication\CategoryService;

class PublicationCategoryCreateTool extends AbstractMcpTool
{
    use BuildsAddress;

    public const NAME = 'publication_category_create';
    public const TITLE = 'Create publication category';
    public const DESCRIPTION = 'Creates a publication category (a section for news or articles). It is HIDDEN from the site (is_public=false) until "is_public" is passed as true. '
        . 'The address is made of the parent address and the title unless "address" is passed. Confirm the structure with the operator first.';
    public const READ_ONLY = false;
    public const SCOPE = 'publication/category';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'title' => $string,
                'parent_uuid' => $string + ['description' => 'Parent category uuid (see publication_category_list); the parent must allow nested categories'],
                'address' => $string + ['description' => 'Latin letters, digits and dashes'],
                'description' => $string,
                'pagination' => ['type' => 'integer', 'description' => 'Records per page, 1-100'],
                'is_allow_nested' => ['type' => 'boolean', 'default' => false],
                'is_public' => ['type' => 'boolean', 'default' => false, 'description' => 'Show the category on the site'],
                'meta_title' => $string,
                'meta_description' => $string,
                'meta_keywords' => $string,
            ],
            'required' => ['title'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $title = $this->textArgument($args, 'title');

        if (blank($title)) {
            throw new \InvalidArgumentException('Field title is required');
        }

        $parent = null;

        if (!blank($args['parent_uuid'] ?? null)) {
            $parent = is_string($args['parent_uuid']) && \Ramsey\Uuid\Uuid::isValid($args['parent_uuid'])
                ? PublicationCategory::query()->where('uuid', $args['parent_uuid'])->first()
                : null;

            if ($parent === null) {
                throw new \InvalidArgumentException('Parent category not found');
            }
            if (!$parent->is_allow_nested) {
                throw new \DomainException('The parent category does not allow nested categories');
            }
        }

        $data = [
            'title' => $title,
            'parent_uuid' => $parent?->uuid,
            'address' => $this->buildAddress((string) ($parent->address ?? ''), $title, $args['address'] ?? null),
            'description' => trim((string) ($args['description'] ?? '')),
            'is_allow_nested' => (bool) ($args['is_allow_nested'] ?? false),
            'is_public' => (bool) ($args['is_public'] ?? false),
            'meta' => array_map('trim', array_filter([
                'title' => $args['meta_title'] ?? null,
                'description' => $args['meta_description'] ?? null,
                'keywords' => $args['meta_keywords'] ?? null,
            ], fn ($value) => $value !== null)),
        ];

        if (isset($args['pagination'])) {
            $data['pagination'] = $this->intArgument($args, 'pagination', 10, 1, 100);
        }

        $category = $this->container->get(CategoryService::class)->create($data);

        $this->container->get(PubSub::class)->publish('api:publication:category:create', $category);
        $this->logger->notice('Publication category created via MCP', ['uuid' => (string) $category->uuid]);

        return $this->describe($category);
    }

    protected function describe(PublicationCategory $category): array
    {
        return [
            'uuid' => (string) $category->uuid,
            'title' => $category->title,
            'address' => $category->address,
            'parent_uuid' => $category->parent_uuid ? (string) $category->parent_uuid : null,
            'is_public' => (bool) $category->is_public,
            'is_allow_nested' => (bool) $category->is_allow_nested,
            'pagination' => $category->pagination,
        ];
    }
}
