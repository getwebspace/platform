<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\Group;

use App\Application\Actions\Cup\User\UserAction;

class DeleteAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $userGroup = $this->userGroupService->read([
                'uuid' => $this->resolveArg('uuid'),
            ]);

            if ($userGroup) {
                $this->userGroupService->delete($userGroup);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:user:group:delete', $userGroup);
            }
        }

        return $this->respondWithRedirect('/cup/user/group');
    }
}
