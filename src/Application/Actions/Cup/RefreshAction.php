<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
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

        return $this->respondWithJson([
            'notification' => $notificationService
                ->read([
                    'user_uuid' => [\Ramsey\Uuid\Uuid::NIL, $user->getUuid()],
                    'order' => ['date' => 'desc'],
                    'limit' => 25,
                ])
                ->map(fn ($item) => $item->toArray()),

            'task' => $taskService
                ->read([
                    'order' => ['date' => 'desc'],
                    'limit' => 25,
                ])
                ->map(fn ($item) => $item->toArray()),
        ]);
    }
}
