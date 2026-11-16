<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\EntityFiles;
use App\Domain\AbstractMcpTool;
use App\Domain\Models\ApiKey;

class EntityFileAttachTool extends AbstractMcpTool
{
    public const NAME = 'entity_file_attach';
    public const TITLE = 'Attach a file to a record';
    public const DESCRIPTION = 'Puts a file (a picture, a document, a sound or a video) on a record: a product, category, publication, publication category, page, user, user group, review or a form submission. '
        . 'Two ways to give the file: "url" - an address on the internet (https, public, up to 20 MB; the best for pictures from a supplier site), or "content_base64" with "name" - the file itself (up to 5 MB; for what the assistant has in hand). '
        . 'Allowed: jpg, png, gif, webp, avif, pdf, doc(x), xls(x), ppt(x), odt, ods, odp, txt, csv, rtf, mp3, mp4, webm - and the content must really be that (a page named .jpg is refused). '
        . 'The same file given twice is stored once. Pictures get their small sizes in the background. The file is visible on the site at once: use dry_run=true first, show the operator what is attached and where, and get confirmation. '
        . 'Order and captions: entity_file_arrange.';
    public const READ_ONLY = false;
    public const PRIVATE = true;

    /**
     * The file is stored on the disk, not in the database: the tool does the dry run itself (checks everything, saves nothing)
     */
    public const DRY_RUN = false;
    public const DRY_RUN_SELF = true;

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
                'url' => $string + ['description' => 'Address of the file on the internet (https://...)'],
                'content_base64' => $string + ['description' => 'The file itself in base64, up to 5 MB'],
                'name' => $string + ['description' => 'Name of the file with the extension, ex: front.jpg; required with content_base64, with url - when the address has no extension'],
                'comment' => $string + ['description' => 'Caption of the file (alt text of a picture), up to 500 characters'],
                'order' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Place among the files of the record; by default - the last'],
            ],
            'required' => ['entity', 'uuid'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $files = new EntityFiles($this->container);
        $record = $files->find($this->apiKey, (string) ($args['entity'] ?? ''), $args['uuid'] ?? null, 'write');

        $order = isset($args['order']) ? $this->intArgument($args, 'order', 1, 1, 100_000) : null;
        $dryRun = !empty($args['dry_run']);

        $result = $files->attach(
            $record,
            array_intersect_key($args, array_flip(['url', 'content_base64', 'name'])),
            isset($args['comment']) ? (string) $args['comment'] : null,
            $order,
            $dryRun
        );

        if (!$dryRun) {
            $this->logger->notice('File attached via MCP', ['entity' => $args['entity'], 'uuid' => (string) $record->getKey(), 'file' => $result['file']['uuid'] ?? null, 'created_file' => $result['created_file']]);
        }

        return ['entity' => $args['entity'], 'uuid' => (string) $record->getKey()] + $result
            + ($dryRun ? ['note' => 'Nothing was saved. Show the operator and repeat the call without dry_run to attach.'] : []);
    }
}
