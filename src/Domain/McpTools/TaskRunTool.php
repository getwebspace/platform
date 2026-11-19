<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\AbstractTask;
use App\Domain\Tasks\ReConvertImageTask;
use App\Domain\Tasks\SearchIndexTask;

class TaskRunTool extends AbstractMcpTool
{
    public const NAME = 'task_run';
    public const TITLE = 'Run a background task';
    public const DESCRIPTION = 'Queues a maintenance task: "search_index" - rebuild the search index, "images" - process the stored images again with the current image settings. '
        . 'Tasks that send mail or data (newsletters, e-mails, JSON hooks) are not offered. Follow the progress with task_list status=all.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const SCOPE = 'task';

    /**
     * The worker is a separate process, it cannot be rolled back
     */
    public const DRY_RUN = false;

    private const TASKS = [
        'search_index' => SearchIndexTask::class,
        'images' => ReConvertImageTask::class,
    ];

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['task' => ['type' => 'string', 'enum' => array_keys(self::TASKS)]],
            'required' => ['task'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $class = self::TASKS[$args['task'] ?? ''] ?? null;

        if ($class === null) {
            throw new \InvalidArgumentException('Field task must be one of: ' . implode(', ', array_keys(self::TASKS)));
        }

        $task = (new $class($this->container))->execute();

        AbstractTask::worker($task->action);

        $this->logger->notice('Task started via MCP', ['task' => (string) $task->uuid, 'action' => $class]);

        return ['task' => (string) $task->uuid, 'title' => $task->title, 'status' => $task->status, 'note' => 'Queued. Check task_list status=all in a minute.'];
    }
}
