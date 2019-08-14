<?php

namespace Resource;

class Form extends \AbstractResource
{
    public function count(array $criteria = [])
    {
        return $this->entityManager->getRepository(\Entity\Form::class)
                    ->count($criteria);
    }

    public function fetch(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        return collect(
            $this->entityManager->getRepository(\Entity\Form::class)
                 ->findBy($criteria, $orderBy, $limit, $offset)
        );
    }

    public function search(array $criteria = [], array $orderBy = [])
    {
        $query = $this->entityManager
                      ->getRepository(\Entity\Form::class)
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
        return $this->entityManager->getRepository(\Entity\Form::class)
                    ->findOneBy($criteria, $orderBy, $limit, $offset);
    }

    public function flush(array $data = [])
    {
        $default = [
            'uuid' => '',
        ];
        $data = array_merge($default, $data);

        $form = $this->fetchOne(['uuid' => $data['uuid']]) ?? new \Entity\Form();
        $form->replace($data);

        $this->entityManager->persist($form);
        $this->entityManager->flush();

        return $form;
    }

    public function remove(array $data = []) {
        $default = [
            'uuid' => '',
        ];
        $data = array_merge($default, $data);

        $form = $this->fetchOne(['uuid' => $data['uuid']]);

        $this->entityManager->remove($form);
        $this->entityManager->flush();

        // todo remove all form data

        return true;
    }
}
