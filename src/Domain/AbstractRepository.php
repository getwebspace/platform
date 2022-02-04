<?php declare(strict_types=1);

namespace App\Domain;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Ramsey\Uuid\UuidInterface as Uuid;

abstract class AbstractRepository extends EntityRepository
{
    public function findOneByUuid(string|Uuid $uuid): mixed
    {
        if (\Ramsey\Uuid\Uuid::isValid((string) $uuid)) {
            $query = $this->createQueryBuilder('en')
                ->andWhere('en.uuid = :uuid')->setParameter('uuid', (string) $uuid, \Ramsey\Uuid\Doctrine\UuidType::NAME)
                ->getQuery();

            try {
                $result = $query->getOneOrNullResult();
            } catch (NonUniqueResultException $e) {
                $results = $query->getResult();
                $result = array_shift($results);
            }

            return $result;
        }

        return null;
    }
}
