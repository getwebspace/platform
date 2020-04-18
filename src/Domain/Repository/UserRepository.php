<?php

namespace App\Domain\Repository;

use App\Domain\Entities\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends EntityRepository
{
    public function findOneByUsername(string $username): ?User
    {
        $query = $this->createQueryBuilder('u')
            ->andWhere('u.username = :username')->setParameter('username', $username, Types::STRING)
            ->getQuery();

        try {
            $result = $query->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            $results = $query->getResult();
            $result = array_shift($results);
        }

        return $result;
    }

    public function findOneByEmail(string $email): ?User
    {
        $query = $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')->setParameter('email', $email, Types::STRING)
            ->getQuery();

        try {
            $result = $query->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            $results = $query->getResult();
            $result = array_shift($results);
        }

        return $result;
    }

    public function findOneByIdentifier(string $identifier): ?User
    {
        $query = $this->createQueryBuilder('u')
            ->orWhere('u.username = :username')->setParameter('username', $identifier, Types::STRING)
            ->orWhere('u.email = :email')->setParameter('email', $identifier, Types::STRING)
            ->getQuery();

        try {
            $result = $query->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            $results = $query->getResult();
            $result = array_shift($results);
        }

        return $result;
    }
}
