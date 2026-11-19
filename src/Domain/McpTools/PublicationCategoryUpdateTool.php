<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\Models\PublicationCategory;
use App\Domain\Service\Publication\CategoryService;

class PublicationCategoryUpdateTool extends PublicationCategoryCreateTool
{
    public const NAME = 'publication_category_update';
    public const TITLE = 'Update publication category';
    public const DESCRIPTION = 'Changes a publication category: title, description, pagination, nesting, visibility (is_public) and SEO. Pass only the fields to change. '
        . 'The address and the parent are kept (moving a category breaks its links). Showing a category on the site is visible to everyone - confirm with the operator first.';

    public function getInputSchema(): array
    {
        $schema = parent::getInputSchema();
        unset($schema['properties']['parent_uuid'], $schema['properties']['address']);
        $schema['properties'] = ['uuid' => ['type' => 'string']] + $schema['properties'];
        $schema['required'] = ['uuid'];

        return $schema;
    }

    public function execute(array $args = []): mixed
    {
        if (!is_string($args['uuid'] ?? null) || !\Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
            throw new \InvalidArgumentException('Field uuid is required');
        }

        /** @var CategoryService $service */
        $service = $this->container->get(CategoryService::class);
        $category = $service->read(['uuid' => $args['uuid']]);
        $data = [];

        if (array_key_exists('title', $args)) {
            if (blank($title = $this->textArgument($args, 'title'))) {
                throw new \InvalidArgumentException('Field title cannot be empty');
            }
            $data['title'] = $title;
        }
        if (array_key_exists('description', $args)) {
            $data['description'] = trim((string) $args['description']);
        }
        foreach (['is_allow_nested', 'is_public'] as $key) {
            if (array_key_exists($key, $args)) {
                $data[$key] = (bool) $args[$key];
            }
        }
        if (isset($args['pagination'])) {
            $data['pagination'] = $this->intArgument($args, 'pagination', 10, 1, 100);
        }

        $meta = array_filter([
            'title' => $args['meta_title'] ?? null,
            'description' => $args['meta_description'] ?? null,
            'keywords' => $args['meta_keywords'] ?? null,
        ], fn ($value) => $value !== null);

        if ($meta) {
            $data['meta'] = array_merge((array) $category->meta, array_map('trim', $meta));
        }
        if (!$data) {
            throw new \InvalidArgumentException('Nothing to change');
        }

        // address generation on update would move the category to another URL
        $data['address'] = $category->address;
        $category = $service->update($category, $data);

        $this->container->get(PubSub::class)->publish('api:publication:category:edit', $category);
        $this->logger->notice('Publication category updated via MCP', ['uuid' => (string) $category->uuid, 'fields' => array_keys($data)]);

        return $this->describe($category) + ['changed_fields' => array_values(array_diff(array_keys($data), ['address']))];
    }
}
