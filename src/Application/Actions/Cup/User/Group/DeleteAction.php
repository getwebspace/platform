<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\Group;

use App\Application\Actions\Cup\User\UserAction;

class DeleteAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $this->userGroupService->delete($this->resolveArg('uuid'));
        }

        return $this->respondWithRedirect('/cup/user/group');
    }
}
