<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use App\Domain\Casts\User\Status as UserStatus;

class UserListAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $q = trim((string) ($this->getParam('q') ?? $this->getParam('username') ?? $this->getParam('email') ?? ''));
        $group = (string) $this->getParam('group_uuid', '');
        $state = (string) $this->getParam('state', 'work');

        $criteria = [
            // by default only working accounts, one select instead of three checkboxes
            'status' => match ($state) {
                'block' => [UserStatus::BLOCK],
                'confirmation' => [UserStatus::CONFIRMATION],
                'delete' => [UserStatus::DELETE],
                'all' => [UserStatus::WORK, UserStatus::BLOCK, UserStatus::CONFIRMATION, UserStatus::DELETE],
                default => [UserStatus::WORK],
            },
            'order' => [
                'group_uuid' => 'desc',
                'register' => 'desc',
            ],
        ];

        if ($q !== '') {
            $criteria['search'] = $q;
        }

        if ($group !== '') {
            $criteria['group_uuid'] = $group;
        }

        return $this->respondWithTemplate('cup/user/index.twig', [
            'list' => $this->userService->read($criteria),
            'groups' => $this->userGroupService->read(['order' => ['title' => 'asc']]),
            'q' => $q,
            'group' => $group,
            'state' => $state,
        ]);
    }
}
