<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Models\CatalogAttribute;
use App\Domain\Service\Catalog\AttributeService;

class CatalogAttributeListTool extends AbstractMcpTool
{
    public const NAME = 'catalog_attribute_list';
    public const TITLE = 'Product attributes';
    public const DESCRIPTION = 'Lists product attributes (characteristics such as colour, size, material) with type, group and whether they are used as a filter in the catalog. Search by title or address.';
    public const SCOPE = 'catalog/attributes';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => ['type' => 'string', 'description' => 'Part of title or address'],
                'is_filter' => ['type' => 'boolean', 'description' => 'Only attributes shown in the catalog filter'],
                'group' => ['type' => 'string', 'description' => 'Exact attribute group'],
            ] + $this->paginationSchema(),
        ];
    }

    public function execute(array $args = []): mixed
    {
        $filter = [];

        if (!blank($search = trim((string) ($args['search'] ?? '')))) {
            $filter['search'] = $search;
        }
        if (isset($args['is_filter'])) {
            $filter['is_filter'] = (bool) $args['is_filter'];
        }
        if (!blank($args['group'] ?? null)) {
            $filter['group'] = [(string) $args['group']];
        }

        /** @var AttributeService $attributeService */
        $attributeService = $this->container->get(AttributeService::class);

        return $this->paginate(
            $args,
            fn (int $limit, int $offset) => $attributeService->read($filter + [
                'order' => ['group' => 'asc', 'title' => 'asc'],
                'limit' => $limit,
                'offset' => $offset,
            ]),
            fn (CatalogAttribute $attribute) => [
                'uuid' => (string) $attribute->uuid,
                'title' => $attribute->title,
                'address' => $attribute->address,
                'type' => $attribute->type,
                'group' => $attribute->group ?: null,
                'is_filter' => (bool) $attribute->is_filter,
            ]
        );
    }
}
