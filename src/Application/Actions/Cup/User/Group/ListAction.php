<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\Group;

use App\Application\Actions\Cup\User\UserAction;

class ListAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/user/group/index.twig', [
            'groups' => $this->userGroupService->read(['order' => ['title' => 'asc']]),
            'users' => $this->userService->read(['status' => \App\Domain\Casts\User\Status::WORK]),
        ]);
    }
}
