<?php

namespace Resource;

class File extends \AbstractResource
{
    public function count(array $criteria = [])
    {
        return $this->entityManager->getRepository(\Entity\File::class)
                    ->count($criteria);
    }

    public function fetch(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        return collect(
            $this->entityManager->getRepository(\Entity\File::class)
                 ->findBy($criteria, $orderBy, $limit, $offset)
        );
    }

    public function fetchOne(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->entityManager->getRepository(\Entity\File::class)
                    ->findOneBy($criteria, $orderBy, $limit, $offset);
    }

    public function flush(\Entity\File $model)
    {
        $this->entityManager->persist($model);
        $this->entityManager->flush();

        return $model;
    }

    public function remove(\Entity\File $model)
    {
        $this->entityManager->remove($model);
        $this->entityManager->flush();

        return true;
    }
}
