<?php declare(strict_types=1);

namespace App\Domain\McpPrompts;

use App\Domain\AbstractMcpPrompt;

class FailedTasksPrompt extends AbstractMcpPrompt
{
    public const NAME = 'check_failed_tasks';
    public const TITLE = 'Check failed background tasks';
    public const DESCRIPTION = 'Find out why background tasks (imports, image conversion...) failed and run again the ones that are worth it.';
    public const TOOLS = ['task_list', 'task_retry'];

    public function render(array $args = []): string
    {
        return "Check the background tasks of the site.\n\n"
            . "1. Call task_list (failed ones by default) and show title, action, date and the reason from the output.\n"
            . "2. Explain each failure in plain words and say whether a repeat can help (a temporary error) or not (a wrong file, missing data).\n"
            . "3. Run again with task_retry only the tasks I confirm. Tasks that send e-mails are refused by the tool - tell me to run those from the admin panel.\n"
            . "4. Look at task_list again after a minute and report the result.\n"
            . $this->rules();
    }
}
