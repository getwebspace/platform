<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Entities\Task;
use App\Domain\Entities\User;
use App\Domain\Service\Notification\NotificationService;
use App\Domain\Service\Task\TaskService;

class RefreshAction extends AbstractAction
{
    protected function action(): \Slim\Http\Response
    {
        /** @var User $user */
        $user = $this->request->getAttribute('user', false);
        $notificationService = NotificationService::getWithContainer($this->container);

        $taskService = TaskService::getWithContainer($this->container);
        $tasks = ['new' => [], 'update' => []];
        $exclude = (array) $this->request->getParam('tasks');
        foreach ($taskService->read(['order' => ['date' => 'desc'], 'limit' => 25])->sortBy('date') as $task) {
            /** @var Task $task */
            if (!in_array($task->getUuid()->toString(), array_keys($exclude), true)) {
                $tasks['new'][] = array_except($task->toArray(), ['params', 'output']);
            } else {
                if (
                    in_array($task->getUuid()->toString(), array_keys($exclude), true) &&
                    (
                        $task->getStatus() !== $exclude[$task->getUuid()->toString()]['status'] ||
                        (int) $task->getProgress() !== (int) $exclude[$task->getUuid()->toString()]['progress']
                    )
                ) {
                    $tasks['update'][] = array_except($task->toArray(), ['params', 'output']);
                }
            }
        }

        return $this->respondWithJson([
            'notification' => $notificationService
                ->read([
                    'user_uuid' => [\Ramsey\Uuid\Uuid::NIL, $user->getUuid()],
                    'order' => ['date' => 'asc'],
                    'limit' => 25,
                ])
                ->whereNotIn('uuid', (array) $this->request->getParam('notifications'))
                ->map(fn ($item) => array_except($item->toArray(), ['params', 'user_uuid']))
                ->values(),

            'task' => $tasks,
        ]);
    }
}
