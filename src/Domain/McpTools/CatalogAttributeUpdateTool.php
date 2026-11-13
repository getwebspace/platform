<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Catalog\Attribute\Type as AttributeType;
use App\Domain\Models\CatalogAttribute;
use App\Domain\Service\Catalog\AttributeService;
use Illuminate\Database\Connection;

class CatalogAttributeUpdateTool extends AbstractMcpTool
{
    public const NAME = 'catalog_attribute_update';
    public const TITLE = 'Update attribute';
    public const DESCRIPTION = 'Changes an attribute (uuid or address from catalog_attribute_list): title, group, the flag "filter" (offer it as a filter in the catalog or not), the address. '
        . 'The type can be changed only while no product has a value of this attribute (the values are kept as text of the type). Pass only the fields to change. '
        . 'Changing the address breaks the links of filters that use it.';
    public const READ_ONLY = false;
    public const SCOPE = 'catalog/attributes';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'attribute' => $string + ['description' => 'Address or uuid of the attribute to change'],
                'title' => $string,
                'type' => ['type' => 'string', 'enum' => AttributeType::LIST],
                'group' => $string,
                'is_filter' => ['type' => 'boolean'],
                'new_address' => $string + ['description' => 'The new address of the attribute (breaks existing filter links)'],
            ],
            'required' => ['attribute'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $key = trim((string) ($args['attribute'] ?? $args['uuid'] ?? ''));

        if ($key === '') {
            throw new \InvalidArgumentException('Field attribute (address or uuid) is required');
        }

        /** @var null|CatalogAttribute $attribute */
        $attribute = CatalogAttribute::all()->first(fn (CatalogAttribute $item) => (string) $item->uuid === $key || $item->address === $key);

        if ($attribute === null) {
            throw new \InvalidArgumentException('Attribute not found, see catalog_attribute_list');
        }

        $data = [];

        if (array_key_exists('title', $args)) {
            if (blank(trim((string) $args['title']))) {
                throw new \InvalidArgumentException('Field title cannot be empty');
            }

            $data['title'] = trim((string) $args['title']);
        }
        if (array_key_exists('group', $args)) {
            $data['group'] = trim((string) $args['group']);
        }
        if (array_key_exists('is_filter', $args)) {
            $data['is_filter'] = (bool) $args['is_filter'];
        }
        if (array_key_exists('type', $args) && $args['type'] !== $attribute->type) {
            if (!in_array($args['type'], AttributeType::LIST, true)) {
                throw new \InvalidArgumentException('Field type must be one of: ' . implode(', ', AttributeType::LIST));
            }

            $used = $this->container->get(Connection::class)->table('catalog_attribute_product')->where('attribute_uuid', (string) $attribute->uuid)->where('value', '!=', '')->count();

            if ($used > 0) {
                throw new \DomainException("The type cannot be changed: {$used} products have a value of this attribute");
            }

            $data['type'] = $args['type'];
        }
        if (!blank($args['new_address'] ?? null)) {
            $data['address'] = trim((string) $args['new_address']);
        }

        if (!$data) {
            throw new \InvalidArgumentException('Nothing to change');
        }

        // address generation on update would change the address of an attribute used in filter links
        $attribute = $this->container->get(AttributeService::class)->update($attribute, $data + ['address' => $attribute->address]);

        $this->container->get(PubSub::class)->publish('api:catalog:attributes:edit', $attribute);
        $this->logger->notice('Attribute updated via MCP', ['uuid' => (string) $attribute->uuid, 'fields' => array_keys($data)]);

        return [
            'uuid' => (string) $attribute->uuid,
            'title' => $attribute->title,
            'address' => $attribute->address,
            'type' => $attribute->type,
            'group' => $attribute->group ?: null,
            'is_filter' => (bool) $attribute->is_filter,
            'changed_fields' => array_keys($data),
        ];
    }
}
