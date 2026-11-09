<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\ThemeFiles;
use App\Domain\AbstractMcpTool;

class ThemeFileRestoreTool extends AbstractMcpTool
{
    public const NAME = 'theme_file_restore';
    public const TITLE = 'Put back a previous version of a file';
    public const DESCRIPTION = 'Returns a template / style file to a version kept by theme_file_history. THE CHANGE IS LIVE ON THE SITE at once. The text it replaces is kept as a version too, so a restore can be undone. '
        . 'Use dry_run=true to check, get the operator\'s confirmation. Works only if the site setting "Edit templates through MCP" is on, with a full access API key.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const DRY_RUN = false;
    public const DRY_RUN_SELF = true;

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string'],
                'version' => ['type' => 'string', 'description' => 'Id from theme_file_history'],
            ],
            'required' => ['path', 'version'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $files = new ThemeFiles($this->container);

        if (!$files->isWriteEnabled()) {
            throw new \DomainException('Editing of templates through MCP is turned off. The administrator turns it on in the site settings (API tab: "Edit templates through MCP")');
        }

        $result = $files->restore((string) ($args['path'] ?? ''), (string) ($args['version'] ?? ''), !empty($args['dry_run']));

        if (!$result['dry_run']) {
            $this->logger->notice('Theme file restored via MCP', ['path' => $result['path'], 'version' => $result['restored']]);
        }

        return $result + ($result['dry_run'] ? ['note' => 'Nothing was changed. Repeat the call without dry_run to apply.'] : []);
    }
}
