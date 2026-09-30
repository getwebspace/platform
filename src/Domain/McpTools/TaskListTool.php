<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Task\Status as TaskStatus;
use App\Domain\Models\Task;
use App\Domain\Service\Task\TaskService;

class TaskListTool extends AbstractMcpTool
{
    public const NAME = 'task_list';
    public const TITLE = 'Background tasks';
    public const DESCRIPTION = 'Lists background tasks (imports, image conversion, mailings, search indexing), newest first, with progress and the output (the reason when a task failed). '
        . 'By default - failed ones. Run a failed task again with task_retry.';
    public const PRIVATE = true;
    public const SCOPE = 'task';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => [...TaskStatus::LIST, 'all'], 'default' => TaskStatus::FAIL],
            ] + $this->paginationSchema(),
        ];
    }

    public function execute(array $args = []): mixed
    {
        $status = $args['status'] ?? TaskStatus::FAIL;

        if ($status !== 'all' && !in_array($status, TaskStatus::LIST, true)) {
            throw new \InvalidArgumentException('Field status must be one of: ' . implode(', ', [...TaskStatus::LIST, 'all']));
        }

        /** @var TaskService $taskService */
        $taskService = $this->container->get(TaskService::class);
        // deleted tasks are hidden unless asked for
        $filter = ['status' => $status === 'all' ? array_values(array_diff(TaskStatus::LIST, [TaskStatus::DELETE])) : $status];

        return $this->paginate(
            $args,
            fn (int $limit, int $offset) => $taskService->read($filter + [
                'order' => ['date' => 'desc'],
                'limit' => $limit,
                'offset' => $offset,
            ]),
            fn (Task $task) => [
                'uuid' => (string) $task->uuid,
                'title' => $task->title,
                'action' => $task->action,
                'status' => $task->status,
                'progress' => $task->progress,
                'date' => $task->date?->format('Y-m-d H:i'),
                'output' => str_truncate((string) $task->output, 500),
            ]
        );
    }
}
