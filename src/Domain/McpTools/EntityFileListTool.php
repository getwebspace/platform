<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\EntityFiles;
use App\Domain\AbstractMcpTool;
use App\Domain\Models\ApiKey;

class EntityFileListTool extends AbstractMcpTool
{
    public const NAME = 'entity_file_list';
    public const TITLE = 'Files of a record';
    public const DESCRIPTION = 'The files attached to a record - pictures of a product or category, documents of a publication, photos of a user or a review - in their order, with the caption, type, size and the link. '
        . 'Kinds of records: catalog_product, catalog_category, publication, publication_category, page, user, user_group, review, form_data (full access key only). Put a file on a record with entity_file_attach.';
    public const PRIVATE = true;

    /**
     * A key that may read the files of at least one kind of records; the kind is checked in execute()
     */
    public function isAllowed(?ApiKey $apiKey): bool
    {
        return EntityFiles::allowsAny($apiKey, 'read');
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity' => ['type' => 'string', 'enum' => array_keys(EntityFiles::ENTITIES)],
                'uuid' => ['type' => 'string', 'description' => 'Uuid of the record'],
            ],
            'required' => ['entity', 'uuid'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $files = new EntityFiles($this->container);
        $record = $files->find($this->apiKey, (string) ($args['entity'] ?? ''), $args['uuid'] ?? null, 'read');

        return ['entity' => $args['entity'], 'uuid' => (string) $record->getKey(), 'files' => $files->list($record)];
    }
}
