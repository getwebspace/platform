<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Models\File;
use App\Domain\Service\File\FileService;

class FileListTool extends AbstractMcpTool
{
    public const NAME = 'file_list';
    public const TITLE = 'Files';
    public const DESCRIPTION = 'Lists uploaded files (images, documents, video), newest first, with type, size and the public link. Search by name; "kind" narrows to images, documents, audio or video.';
    public const PRIVATE = true;
    public const SCOPE = 'file';

    private const KINDS = [
        'image' => 'image/',
        'audio' => 'audio/',
        'video' => 'video/',
        'document' => ['application/', 'text/'],
    ];

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => ['type' => 'string', 'description' => 'Part of the file name'],
                'kind' => ['type' => 'string', 'enum' => array_keys(self::KINDS)],
            ] + $this->paginationSchema(),
        ];
    }

    public function execute(array $args = []): mixed
    {
        $kind = $args['kind'] ?? null;

        if ($kind !== null && !isset(self::KINDS[$kind])) {
            throw new \InvalidArgumentException('Field kind must be one of: ' . implode(', ', array_keys(self::KINDS)));
        }

        $filter = [];

        if (!blank($search = trim((string) ($args['search'] ?? '')))) {
            $filter['search'] = $search;
        }

        /** @var FileService $fileService */
        $fileService = $this->container->get(FileService::class);
        $homepage = rtrim((string) $this->parameter('common_homepage', ''), '/');
        $prefixes = $kind === null ? [] : (array) self::KINDS[$kind];

        return $this->paginate(
            $args,
            function (int $limit, int $offset) use ($fileService, $filter, $prefixes) {
                $filter += ['order' => ['date' => 'desc']];

                // no kind: the database pages the list
                if (!$prefixes) {
                    return $fileService->read($filter + ['limit' => $limit, 'offset' => $offset]);
                }

                // the service has no mime filter: sort out by the type, the page is cut after that
                return $fileService->read($filter)
                    ->filter(fn (File $file) => collect($prefixes)->contains(fn ($prefix) => str_starts_with((string) $file->type, $prefix)))
                    ->slice($offset, $limit)
                    ->values();
            },
            fn (File $file) => [
                'uuid' => (string) $file->uuid,
                'name' => $file->filename(),
                'type' => $file->type,
                'size' => $file->size(),
                'date' => datetime($file->date)->format('Y-m-d H:i'),
                'url' => $homepage . $file->public_path(),
            ]
        );
    }
}
