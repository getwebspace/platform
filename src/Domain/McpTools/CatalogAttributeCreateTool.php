<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Attribute\Type as AttributeType;
use App\Domain\McpTools\Concerns\BuildsAddress;
use App\Domain\Service\Catalog\AttributeService;

class CatalogAttributeCreateTool extends AbstractMcpTool
{
    use BuildsAddress;

    public const NAME = 'catalog_attribute_create';
    public const TITLE = 'Create attribute';
    public const DESCRIPTION = 'Adds an attribute: a characteristic of products (colour, size, material) with a type (string, integer, float, boolean), an optional group and the flag "filter" - '
        . 'whether it is offered as a filter in the catalog. Then put it into categories (catalog_category_update attributes_add) and give values to products (catalog_product_update attributes). '
        . 'The address is made of the title unless "address" is passed.';
    public const READ_ONLY = false;
    public const SCOPE = 'catalog/attributes';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'title' => $string,
                'type' => ['type' => 'string', 'enum' => AttributeType::LIST, 'default' => AttributeType::STRING],
                'group' => $string + ['description' => 'Group of attributes in the lists, ex: Dimensions'],
                'is_filter' => ['type' => 'boolean', 'default' => true, 'description' => 'Offer it as a filter in the catalog'],
                'address' => $string + ['description' => 'Latin letters, digits and dashes; made of the title by default'],
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

        $type = $args['type'] ?? AttributeType::STRING;

        if (!in_array($type, AttributeType::LIST, true)) {
            throw new \InvalidArgumentException('Field type must be one of: ' . implode(', ', AttributeType::LIST));
        }

        $attribute = $this->container->get(AttributeService::class)->create([
            'title' => $title,
            'address' => $this->buildAddress('', $title, $args['address'] ?? null),
            'type' => $type,
            'group' => trim((string) ($args['group'] ?? '')),
            'is_filter' => ($args['is_filter'] ?? true) !== false,
        ]);

        $this->container->get(PubSub::class)->publish('api:catalog:attributes:create', $attribute);
        $this->logger->notice('Attribute created via MCP', ['uuid' => (string) $attribute->uuid]);

        return [
            'uuid' => (string) $attribute->uuid,
            'title' => $attribute->title,
            'address' => $attribute->address,
            'type' => $attribute->type,
            'group' => $attribute->group ?: null,
            'is_filter' => (bool) $attribute->is_filter,
        ];
    }
}
