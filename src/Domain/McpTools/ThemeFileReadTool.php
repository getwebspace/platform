<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\ThemeFiles;
use App\Domain\AbstractMcpTool;

class ThemeFileReadTool extends AbstractMcpTool
{
    public const NAME = 'theme_file_read';
    public const TITLE = 'Read a template or style file';
    public const DESCRIPTION = 'The text of a template, style or script (path from theme_file_list, ex: theme/page.twig) with its "version". '
        . 'The version is needed to change the file with theme_file_write: it proves the change is made on the text that was read. Files up to 300 KB. Requires a full access API key.';
    public const PRIVATE = true;

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'ex: theme/page.twig or resource/css/style.css'],
            ],
            'required' => ['path'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        return (new ThemeFiles($this->container))->read((string) ($args['path'] ?? ''));
    }
}
