<?php

namespace Resource\User;

class Session extends \AbstractResource
{
    public function count(array $criteria = [])
    {
        return $this->entityManager->getRepository(\Entity\User\Session::class)
                    ->count($criteria);
    }

    public function fetch(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        return collect(
            $this->entityManager->getRepository(\Entity\User\Session::class)
                 ->findBy($criteria, $orderBy, $limit, $offset)
        );
    }

    public function fetchOne(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->entityManager->getRepository(\Entity\User\Session::class)
                    ->findOneBy($criteria, $orderBy, $limit, $offset);
    }

    public function flush(array $data = [])
    {
        $default = [
            'uuid' => '',
        ];
        $data = array_merge($default, $data);

        $userSession = $this->fetchOne(['uuid' => $data['uuid']]) ?? new \Entity\User\Session();
        $userSession->replace($data);

        $this->entityManager->persist($userSession);
        $this->entityManager->flush();

        return $userSession;
    }
}
