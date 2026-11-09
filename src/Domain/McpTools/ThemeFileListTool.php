<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\ThemeFiles;
use App\Domain\AbstractMcpTool;

class ThemeFileListTool extends AbstractMcpTool
{
    public const NAME = 'theme_file_list';
    public const TITLE = 'Template and style files';
    public const DESCRIPTION = 'Files of the site design: templates of the active theme (theme/...) and styles and scripts (resource/...), the same as the admin panel editor shows. '
        . 'Search by a part of the path. Only text files that tools may edit are listed unless editable_only=false. Read with theme_file_read. Requires a full access API key.';
    public const PRIVATE = true;

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'section' => ['type' => 'string', 'enum' => ['theme', 'resource'], 'description' => 'theme - templates of the active theme, resource - css / js / images'],
                'search' => ['type' => 'string', 'description' => 'Part of the path, ex: product'],
                'editable_only' => ['type' => 'boolean', 'default' => true],
            ] + $this->paginationSchema(),
        ];
    }

    public function execute(array $args = []): mixed
    {
        $section = $args['section'] ?? null;

        if ($section !== null && !in_array($section, ['theme', 'resource'], true)) {
            throw new \InvalidArgumentException('Field section must be theme or resource');
        }

        $files = (new ThemeFiles($this->container))->list($section, trim((string) ($args['search'] ?? '')), ($args['editable_only'] ?? true) !== false);

        return ['total' => count($files)] + $this->paginate(
            $args,
            fn (int $limit, int $offset) => collect(array_slice($files, $offset, $limit)),
            fn (array $file) => $file
        );
    }
}
