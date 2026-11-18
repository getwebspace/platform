<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\Group;

use App\Domain\Casts\User\Status as UserStatus;
use App\Application\Actions\Cup\User\UserAction;
use App\Domain\Service\User\Exception\UserGroupNotFoundException;

class DeleteAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $userGroup = $this->userGroupService->read([
                    'uuid' => $this->resolveArg('uuid'),
                ]);

                // a group with members stays: they would be left with a group that does not exist, and no access at all
                $members = $this->userService->read([
                    'group_uuid' => $userGroup->uuid,
                    'status' => [UserStatus::WORK, UserStatus::BLOCK, UserStatus::CONFIRMATION],
                ])->count();

                if ($members === 0) {
                    $this->userGroupService->delete($userGroup);

                    $this->container->get(\App\Application\PubSub::class)->publish('cup:user:group:delete', $userGroup);
                }
            } catch (UserGroupNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/user/group');
    }
}
