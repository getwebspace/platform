<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

class UserListAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $criteria = [
            'status' => [\App\Domain\Types\UserStatusType::STATUS_WORK],
        ];

        if ($this->isPost()) {
            $data = [
                'username' => $this->getParam('username'),
                'username_strong' => $this->getParam('username_strong'),
                'email' => $this->getParam('email'),
                'status_block' => $this->getParam('status_block'),
                'status_delete' => $this->getParam('status_delete'),
            ];

            if ($data['username']) {
                $criteria['username'] = str_escape($data['username']);

                if (!$data['username_strong']) {
                    $criteria['username'] = '%' . $criteria['username'] . '%';
                }
            }

            if ($data['email']) {
                $criteria['email'] = str_escape($data['email']);
            }

            if ($data['status_block']) {
                $criteria['status'][] = \App\Domain\Types\UserStatusType::STATUS_BLOCK;
            }

            if ($data['status_delete']) {
                $criteria['status'][] = \App\Domain\Types\UserStatusType::STATUS_DELETE;
            }
        }

        $query = $this->userService->createQueryBuilder('u');

        foreach ($criteria as $criterion => $value) {
            if (is_array($value)) {
                $query->andWhere("u.{$criterion} IN (:{$criterion})");
            } elseif (!str_starts_with($value, '%')) {
                $query->andWhere("u.{$criterion} = :{$criterion}");
            } else {
                $query->andWhere("u.{$criterion} LIKE :{$criterion}");
            }
            $query->setParameter($criterion, $value);
        }

        return $this->respondWithTemplate('cup/user/index.twig', [
            'list' => collect($query->getQuery()->getResult()),
        ]);
    }
}
