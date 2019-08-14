<?php

namespace Resource\Form;

class Data extends \AbstractResource
{
    public function count(array $criteria = [])
    {
        return $this->entityManager->getRepository(\Entity\Form\Data::class)
                    ->count($criteria);
    }

    public function fetch(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        return collect(
            $this->entityManager->getRepository(\Entity\Form\Data::class)
                 ->findBy($criteria, $orderBy, $limit, $offset)
        );
    }

    public function fetchOne(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->entityManager->getRepository(\Entity\Form\Data::class)
                    ->findOneBy($criteria, $orderBy, $limit, $offset);
    }

    public function search(array $criteria = [], array $orderBy = [])
    {
        $query = $this->entityManager
                      ->getRepository(\Entity\Form\Data::class)
                      ->createQueryBuilder('c');

        foreach ($criteria as $criterion => $value) {
            if (is_array($value)) {
                $query->andWhere("c.{$criterion} IN ('" . implode("', '", $value) . "')");
            } else if (strpos($value, '%') === false) {
                $query->andWhere("c.{$criterion} = '{$value}'");
            } else {
                $query->andWhere("c.{$criterion} LIKE '{$value}'");
            }
        }

        foreach ($orderBy as $field => $direction) {
            $query->orderBy("c.{$field}", $direction);
        }

        return collect($query->getQuery()->getResult());
    }

    public function flush(array $data = [])
    {
        $default = [
            'uuid' => '',
        ];
        $data = array_merge($default, $data);

        $category = $this->fetchOne(['uuid' => $data['uuid']]) ?? new \Entity\Form\Data();
        $category->replace($data);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    public function remove(array $data = []) {
        $default = [
            'form_uuid' => '',
            'uuid' => '',
        ];
        $data = array_merge($default, $data);

        $page = $this->fetchOne(['form_uuid' => $data['form_uuid'], 'uuid' => $data['uuid']]);

        $this->entityManager->remove($page);
        $this->entityManager->flush();

        return true;
    }
}
