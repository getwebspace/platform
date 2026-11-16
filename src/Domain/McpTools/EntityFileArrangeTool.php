<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\EntityFiles;
use App\Domain\AbstractMcpTool;
use App\Domain\Models\ApiKey;

class EntityFileArrangeTool extends AbstractMcpTool
{
    public const NAME = 'entity_file_arrange';
    public const TITLE = 'Order and captions of the files of a record';
    public const DESCRIPTION = 'Sets the order of the files of a record (the first one is the main picture) and the captions of the files. "order" - the uuids of the files from entity_file_list in the wanted order, '
        . 'the ones that are not listed go after them; "comments" - {file uuid: caption}. Pass one or both. Nothing is added or removed.';
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
                'order' => ['type' => 'array', 'items' => $string, 'description' => 'Uuids of the files in the wanted order'],
                'comments' => ['type' => 'object', 'description' => '{"file uuid": "caption"}; "" clears a caption'],
            ],
            'required' => ['entity', 'uuid'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $order = $args['order'] ?? null;
        $comments = $args['comments'] ?? null;

        if ($order === null && $comments === null) {
            throw new \InvalidArgumentException('Pass order and/or comments');
        }
        if (($order !== null && !is_array($order)) || ($comments !== null && (!is_array($comments) || ($comments !== [] && array_is_list($comments))))) {
            throw new \InvalidArgumentException('order is a list of file uuids, comments is an object {"file uuid": "caption"}');
        }

        $files = new EntityFiles($this->container);
        $record = $files->find($this->apiKey, (string) ($args['entity'] ?? ''), $args['uuid'] ?? null, 'write');

        $this->logger->notice('Files arranged via MCP', ['entity' => $args['entity'], 'uuid' => (string) $record->getKey()]);

        return ['entity' => $args['entity'], 'uuid' => (string) $record->getKey(), 'files' => $files->arrange($record, $order, $comments)];
    }
}
