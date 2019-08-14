<?php

namespace Resource\Catalog;

class Category extends \AbstractResource
{
    public function count(array $criteria = [])
    {
        return $this->entityManager->getRepository(\Entity\Catalog\Category::class)
                    ->count($criteria);
    }

    public function fetch(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        return collect(
            $this->entityManager->getRepository(\Entity\Catalog\Category::class)
                 ->findBy($criteria, $orderBy, $limit, $offset)
        );
    }

    public function search(array $criteria = [], array $orderBy = [])
    {
        $query = $this->entityManager
                      ->getRepository(\Entity\Catalog\Category::class)
                      ->createQueryBuilder('p');

        foreach ($criteria as $criterion => $value) {
            if (is_array($value)) {
                $query->andWhere("p.{$criterion} IN ('" . implode("', '", $value) . "')");
            } else if (strpos($value, '%') === false) {
                $query->andWhere("p.{$criterion} = '{$value}'");
            } else {
                $query->andWhere("p.{$criterion} LIKE '{$value}'");
            }
        }

        foreach ($orderBy as $field => $direction) {
            $query->orderBy("u.{$field}", $direction);
        }

        return collect($query->getQuery()->getResult());
    }

    public function fetchOne(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->entityManager->getRepository(\Entity\Catalog\Category::class)
                    ->findOneBy($criteria, $orderBy, $limit, $offset);
    }

    public function flush(array $data = [])
    {
        $default = [
            'uuid' => '',
        ];
        $data = array_merge($default, $data);

        $category = $this->fetchOne(['uuid' => $data['uuid']]) ?? new \Entity\Catalog\Category();
        $category->replace($data);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    public function remove(array $data = []) {
        $default = [
            'uuid' => '',
        ];
        $data = array_merge($default, $data);

        $category = $this->fetchOne(['uuid' => $data['uuid']]);

        $this->entityManager->remove($category);
        $this->entityManager->flush();

        return true;
    }
}
