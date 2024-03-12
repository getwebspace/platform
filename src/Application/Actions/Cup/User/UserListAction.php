<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use App\Domain\Enums\UserStatus;
use App\Domain\Models\User;

class UserListAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $criteria = [
            'status' => [UserStatus::WORK],
        ];

        $query = User::query();

        if ($this->isPost()) {
            $data = [
                'username' => $this->getParam('username'),
                'username_strong' => $this->getParam('username_strong'),
                'email' => $this->getParam('email'),
                'group_uuid' => $this->getParam('group_uuid'),
                'status_block' => $this->getParam('status_block'),
                'status_delete' => $this->getParam('status_delete'),
            ];

            if ($data['username']) {
                if (!$data['username_strong']) {
                    $query = $query->where('username', 'like', '%' . $data['username'] . '%');
                } else {
                    $query = $query->where('username', '=', $data['username']);
                }
            }

            if ($data['email']) {
                $query = $query->where('email', '=', $data['email']);
            }

            if ($data['group_uuid']) {
                $query = $query->where('group_uuid', '=', $data['group_uuid']);
            }

            if ($data['status_block']) {
                $criteria['status'][] = UserStatus::BLOCK;
            }

            if ($data['status_delete']) {
                $criteria['status'][] = UserStatus::DELETE;
            }
        }

        $query = $query
            ->whereIn('status', $criteria['status'])
            ->orderBy('group_uuid', 'desc')
            ->orderBy('register', 'desc');

        return $this->respondWithTemplate('cup/user/index.twig', [
            'list' => $query->get(),
            'groups' => $this->userGroupService->read(['order' => ['title' => 'asc']]),
        ]);
    }
}
