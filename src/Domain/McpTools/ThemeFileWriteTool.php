<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\ThemeFiles;
use App\Domain\AbstractMcpTool;

class ThemeFileWriteTool extends AbstractMcpTool
{
    public const NAME = 'theme_file_write';
    public const TITLE = 'Change a template or style file';
    public const DESCRIPTION = 'Writes the whole new text of a template (.twig), style (.css), script (.js) or text file of the design. THE CHANGE IS LIVE ON THE SITE at once for every visitor. '
        . 'Read the file first (theme_file_read) and pass its "version" as expected_version - a file changed meanwhile is not overwritten. A template with a syntax error is refused. '
        . 'The previous text is kept (theme_file_history, theme_file_restore). A new file is created without expected_version. Always run with dry_run=true first and show the operator what changes '
        . '(lines added / removed), then get explicit confirmation. Works only if the site setting "Edit templates through MCP" is on, with a full access API key.';
    public const READ_ONLY = false;
    public const PRIVATE = true;

    /**
     * Files are not in the database: the dry run is made by the tool itself
     */
    public const DRY_RUN = false;
    public const DRY_RUN_SELF = true;

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'ex: theme/page.twig, resource/css/style.css'],
                'content' => ['type' => 'string', 'description' => 'The WHOLE new text of the file'],
                'expected_version' => ['type' => 'string', 'description' => 'The "version" from theme_file_read; leave empty for a new file'],
            ],
            'required' => ['path', 'content'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $files = new ThemeFiles($this->container);

        if (!is_string($args['content'] ?? null)) {
            throw new \InvalidArgumentException('Field content is required');
        }
        if (!$files->isWriteEnabled()) {
            throw new \DomainException('Editing of templates through MCP is turned off. The administrator turns it on in the site settings (API tab: "Edit templates through MCP")');
        }

        $result = $files->write((string) ($args['path'] ?? ''), $args['content'], $args['expected_version'] ?? null, !empty($args['dry_run']));

        if (!$result['dry_run']) {
            $this->logger->notice('Theme file written via MCP', ['path' => $result['path'], 'created' => $result['created'], 'added' => $result['added'], 'removed' => $result['removed'], 'backup' => $result['backup']]);
        }

        return $result + ($result['dry_run'] ? ['note' => 'Nothing was written. Show the operator the change and repeat the call without dry_run to apply.'] : []);
    }
}
