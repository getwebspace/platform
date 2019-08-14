<?php

namespace Resource;

class User extends \AbstractResource
{
    public function count(array $criteria = [])
    {
        return $this->entityManager->getRepository(\Entity\User::class)
                    ->count($criteria);
    }

    public function fetch(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        return collect(
            $this->entityManager->getRepository(\Entity\User::class)
                 ->findBy($criteria, $orderBy, $limit, $offset)
        );
    }

    public function search(array $criteria = [], array $orderBy = [])
    {
        $query = $this->entityManager
                      ->getRepository(\Entity\User::class)
                      ->createQueryBuilder('u');

        foreach ($criteria as $criterion => $value) {
            if (is_array($value)) {
                $query->andWhere("u.{$criterion} IN ('" . implode("', '", $value) . "')");
            } else if (strpos($value, '%') === false) {
                $query->andWhere("u.{$criterion} = '{$value}'");
            } else {
                $query->andWhere("u.{$criterion} LIKE '{$value}'");
            }
        }

        foreach ($orderBy as $field => $direction) {
            $query->orderBy("u.{$field}", $direction);
        }

        return collect($query->getQuery()->getResult());
    }

    public function fetchOne(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->entityManager->getRepository(\Entity\User::class)
                    ->findOneBy($criteria, $orderBy, $limit, $offset);
    }

    public function flush(array $data = [])
    {
        $default = [
            'uuid' => '',
        ];
        $data = array_merge($default, $data);

        $user = $this->fetchOne(['uuid' => $data['uuid']]) ?? new \Entity\User();
        $user->replace($data);
        $user->change = new \DateTime();
        $this->entityManager->persist($user);

        $session = $user->session ?? new \Entity\User\Session([
            'uuid' => $user->uuid,
        ]);
        $this->entityManager->persist($session);
        $user->session = $session;

        $this->entityManager->flush();

        return $user;
    }
}
