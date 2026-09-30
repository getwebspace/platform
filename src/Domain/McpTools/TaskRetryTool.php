<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\AbstractTask;
use App\Domain\Casts\Task\Status as TaskStatus;
use App\Domain\Service\Task\TaskService;

class TaskRetryTool extends AbstractMcpTool
{
    public const NAME = 'task_retry';
    public const TITLE = 'Run failed task again';
    public const DESCRIPTION = 'Puts a failed or cancelled task back to the queue and starts the worker. Same parameters, same task. '
        . 'Tasks that send e-mails are refused (to avoid duplicates for people) - run them from the admin panel. Look at the reason with task_list first.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const SCOPE = 'task';

    /**
     * Tasks whose repeat could reach people twice
     */
    private const FORBIDDEN = [
        \App\Domain\Tasks\SendMailTask::class,
        \App\Domain\Tasks\SendNewsLetterMailTask::class,
    ];

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uuid' => ['type' => 'string', 'description' => 'Uuid from task_list'],
            ],
            'required' => ['uuid'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        if (!is_string($args['uuid'] ?? null) || !\Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
            throw new \InvalidArgumentException('Field uuid is required');
        }

        /** @var TaskService $taskService */
        $taskService = $this->container->get(TaskService::class);
        $task = $taskService->read(['uuid' => $args['uuid']]);

        if (!in_array($task->status, [TaskStatus::FAIL, TaskStatus::CANCEL], true)) {
            throw new \DomainException("Only failed or cancelled tasks can be run again, this one is: {$task->status}");
        }
        if (in_array($task->action, self::FORBIDDEN, true)) {
            throw new \DomainException('Tasks sending e-mails are not repeated here, run it from the admin panel');
        }
        if (!class_exists($task->action) || !is_subclass_of($task->action, AbstractTask::class)) {
            throw new \DomainException('Unknown task action');
        }

        $taskService->update($task, ['status' => TaskStatus::QUEUE, 'progress' => 0, 'output' => '']);
        AbstractTask::worker($task->action);

        $this->logger->notice('Task run again via MCP', ['uuid' => (string) $task->uuid, 'action' => $task->action]);

        return [
            'uuid' => (string) $task->uuid,
            'title' => $task->title,
            'status' => TaskStatus::QUEUE,
            'note' => 'Queued, the worker is started. Check task_list in a minute.',
        ];
    }
}
