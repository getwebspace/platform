<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

class UserListAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        $criteria = [
            'status' => [\App\Domain\Types\UserStatusType::STATUS_WORK],
        ];
        $orderBy = [];

        if ($this->request->isPost()) {
            $data = [
                'username' => $this->request->getParam('username'),
                'username_strong' => $this->request->getParam('username_strong'),
                'email' => $this->request->getParam('email'),
                'status_block' => $this->request->getParam('status_block'),
                'status_delete' => $this->request->getParam('status_delete'),
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

        $query = $this->users->createQueryBuilder('u');

        foreach ($criteria as $criterion => $value) {
            if (is_array($value)) {
                $query->andWhere("u.{$criterion} IN ('" . implode("', '", $value) . "')");
            } elseif (mb_strpos($value, '%') === false) {
                $query->andWhere("u.{$criterion} = '{$value}'");
            } else {
                $query->andWhere("u.{$criterion} LIKE '{$value}'");
            }
        }

        foreach ($orderBy as $field => $direction) {
            $query->orderBy("u.{$field}", $direction);
        }

        $list = collect($query->getQuery()->getResult());

        return $this->respondWithTemplate('cup/user/index.twig', ['list' => $list]);
    }
}
