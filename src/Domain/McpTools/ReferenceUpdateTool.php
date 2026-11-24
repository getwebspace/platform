<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\Models\Reference;
use App\Domain\Service\Reference\ReferenceService;

class ReferenceUpdateTool extends ReferenceCreateTool
{
    public const NAME = 'reference_update';
    public const TITLE = 'Update reference record';
    public const DESCRIPTION = 'Changes a reference book record (uuid from reference_list): title, value (replaces the whole value), order, status (false disables it, nothing is deleted). '
        . 'The type is never changed. Orders already placed keep pointing at the record, so confirm with the operator first.';

    public function getInputSchema(): array
    {
        $schema = parent::getInputSchema();
        unset($schema['properties']['type']);
        $schema['properties'] = ['uuid' => ['type' => 'string']] + $schema['properties'];
        $schema['required'] = ['uuid'];

        return $schema;
    }

    public function execute(array $args = []): mixed
    {
        if (!is_string($args['uuid'] ?? null) || !\Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
            throw new \InvalidArgumentException('Field uuid is required');
        }

        /** @var ReferenceService $service */
        $service = $this->container->get(ReferenceService::class);
        $reference = $service->read(['uuid' => $args['uuid']]);

        if (!in_array($reference->type, self::TYPES, true)) {
            throw new \DomainException('Records of this type can not be changed here');
        }

        $data = [];

        if (array_key_exists('title', $args)) {
            if (blank($title = $this->textArgument($args, 'title'))) {
                throw new \InvalidArgumentException('Field title cannot be empty');
            }
            $data['title'] = $title;
        }
        if (array_key_exists('value', $args)) {
            if (!is_array($args['value'])) {
                throw new \InvalidArgumentException('Field value must be an object');
            }
            $data['value'] = $args['value'];
        }
        foreach (['description', 'kind'] as $field) {
            if (array_key_exists($field, $args)) {
                $data[$field] = $this->textArgument($args, $field);
            }
        }
        if (isset($args['order'])) {
            $data['order'] = $this->intArgument($args, 'order', 1, 0, 100000);
        }
        if (array_key_exists('status', $args)) {
            $data['status'] = (bool) $args['status'];
        }
        if (!$data) {
            throw new \InvalidArgumentException('Nothing to change');
        }

        $reference = $service->update($reference, $data);

        $this->container->get(PubSub::class)->publish('api:reference:edit', $reference);
        $this->logger->notice('Reference updated via MCP', ['uuid' => (string) $reference->uuid, 'fields' => array_keys($data)]);

        return $this->describe($reference) + ['changed_fields' => array_keys($data)];
    }
}
