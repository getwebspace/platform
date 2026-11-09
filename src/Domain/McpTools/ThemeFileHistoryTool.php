<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\ThemeFiles;
use App\Domain\AbstractMcpTool;

class ThemeFileHistoryTool extends AbstractMcpTool
{
    public const NAME = 'theme_file_history';
    public const TITLE = 'Previous versions of a file';
    public const DESCRIPTION = 'The versions of a template / style file that were kept before changes made through tools (the last 20), newest first. Put one back with theme_file_restore. Requires a full access API key.';
    public const PRIVATE = true;

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'ex: theme/page.twig'],
            ],
            'required' => ['path'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $path = (string) ($args['path'] ?? '');

        return ['path' => $path, 'versions' => (new ThemeFiles($this->container))->backups($path)];
    }
}
