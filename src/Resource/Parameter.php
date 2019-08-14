<?php

namespace Resource;

class Parameter extends \AbstractResource
{
    public function count(array $criteria = [])
    {
        return $this->entityManager->getRepository(\Entity\Parameter::class)
            ->count($criteria);
    }

    public function fetch(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        $params = collect(
                      $this->entityManager->getRepository(\Entity\Parameter::class)
                           ->findBy($criteria, $orderBy, $limit, $offset)
                  )
                  ->mapWithKeys(function ($item) {
                      list($group, $key) = explode('_', $item->key, 2);
                      return [$group . '[' . $key . ']' => $item];
                  });

        return $params;
    }

    public function fetchOne(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->entityManager->getRepository(\Entity\Parameter::class)
                    ->findOneBy($criteria, $orderBy, $limit, $offset);
    }

    public function flush(array $data = [])
    {
        $default = [
            'key' => '',
        ];
        $data = array_merge($default, $data);

        $param = $this->fetchOne(['key' => $data['key']]) ?? new \Entity\Parameter();
        $param->replace($data);

        $this->entityManager->persist($param);
        $this->entityManager->flush();

        return $param;
    }
}
