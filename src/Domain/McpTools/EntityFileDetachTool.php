<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\EntityFiles;
use App\Domain\AbstractMcpTool;
use App\Domain\Models\ApiKey;

class EntityFileDetachTool extends AbstractMcpTool
{
    public const NAME = 'entity_file_detach';
    public const TITLE = 'Take a file away from a record';
    public const DESCRIPTION = 'Takes a file off a record (the uuid from entity_file_list): it is not shown there any more. The file itself stays in the file store - it may be used by other records and can be deleted '
        . 'in the file manager of the admin panel when nobody needs it (the answer says if it is still used somewhere). Use dry_run=true and get the operator\'s confirmation first.';
    public const READ_ONLY = false;
    public const PRIVATE = true;

    public function isAllowed(?ApiKey $apiKey): bool
    {
        return EntityFiles::allowsAny($apiKey, 'write');
    }

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'entity' => ['type' => 'string', 'enum' => array_keys(EntityFiles::ENTITIES)],
                'uuid' => $string + ['description' => 'Uuid of the record'],
                'file' => $string + ['description' => 'Uuid of the file'],
            ],
            'required' => ['entity', 'uuid', 'file'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $files = new EntityFiles($this->container);
        $record = $files->find($this->apiKey, (string) ($args['entity'] ?? ''), $args['uuid'] ?? null, 'write');

        $result = $files->detach($record, (string) ($args['file'] ?? ''));

        $this->logger->notice('File detached via MCP', ['entity' => $args['entity'], 'uuid' => (string) $record->getKey(), 'file' => $args['file']]);

        return ['entity' => $args['entity'], 'uuid' => (string) $record->getKey()] + $result;
    }
}
