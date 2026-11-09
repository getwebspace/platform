<?php declare(strict_types=1);

namespace App\Domain\McpPrompts;

use App\Domain\AbstractMcpPrompt;

class EditTemplatePrompt extends AbstractMcpPrompt
{
    public const NAME = 'edit_template';
    public const TITLE = 'Change a template or a style';
    public const DESCRIPTION = 'Change the design of the site (a template, a style, a script) safely: read, propose, check, apply, with a way back.';
    public const TOOLS = ['theme_file_list', 'theme_file_read', 'theme_file_write', 'theme_file_history', 'theme_file_restore'];

    public function getArguments(): array
    {
        return [
            ['name' => 'task', 'description' => 'What to change, ex: "make the phone in the header bigger"', 'required' => true],
            ['name' => 'file', 'description' => 'Path of the file if known, ex: theme/layout1.twig', 'required' => false],
        ];
    }

    public function render(array $args = []): string
    {
        $task = trim((string) ($args['task'] ?? ''));
        $file = trim((string) ($args['file'] ?? ''));

        if ($task === '') {
            throw new \InvalidArgumentException('Argument task is required');
        }

        return "Change the design of the site: {$task}\n\n"
            . ($file !== '' ? "1. Read the file {$file} with theme_file_read.\n" : "1. Find the file: theme_file_list with a part of the name (theme/ is templates, resource/ is styles and scripts), then read it with theme_file_read. If you are not sure which file, ask me.\n")
            . "2. Do the smallest change that does the job. A template is Twig: keep every {% ... %} and {{ ... }} that you do not have to touch, do not remove blocks that other templates fill (\"extends\" / \"block\").\n"
            . "3. Run theme_file_write with dry_run=true, the whole new text and expected_version from the reading. Show me what is going to change - the lines that go and the lines that come, in plain words, not the whole file.\n"
            . "4. A template change is LIVE for every visitor at once. Only after my explicit yes call theme_file_write again without dry_run.\n"
            . "5. Tell me the file and the \"backup\" id from the result: theme_file_restore with it brings the old text back.\n"
            . "6. If theme_file_write says editing is turned off, tell me where to turn it on (site settings, API tab) and stop.\n"
            . $this->rules();
    }
}
