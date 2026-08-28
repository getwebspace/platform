<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use App\Domain\Casts\User\Status as UserStatus;

class UserListAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $criteria = [
            'status' => [UserStatus::WORK],
            'order' => [
                'group_uuid' => 'desc',
                'register' => 'desc',
            ],
        ];

        if ($this->isPost()) {
            $username = $this->getParam('username');
            $email = $this->getParam('email');
            $group_uuid = $this->getParam('group_uuid');

            if ($username) {
                if ($this->getParam('username_strong')) {
                    // a scalar makes read() look up a single user, a list filter
                    // has to be passed as an array
                    $criteria['username'] = [$username];
                } else {
                    $criteria['search'] = $username;
                }
            }

            if ($email) {
                $criteria['email'] = [$email];
            }

            if ($group_uuid) {
                $criteria['group_uuid'] = $group_uuid;
            }

            if ($this->getParam('status_block')) {
                $criteria['status'][] = UserStatus::BLOCK;
            }

            if ($this->getParam('status_delete')) {
                $criteria['status'][] = UserStatus::DELETE;
            }
        }

        return $this->respondWithTemplate('cup/user/index.twig', [
            'list' => $this->userService->read($criteria),
            'groups' => $this->userGroupService->read(['order' => ['title' => 'asc']]),
        ]);
    }
}
