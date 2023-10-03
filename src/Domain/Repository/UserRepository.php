<?php declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\AbstractRepository;
use App\Domain\Entities\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|User findOneByUuid($uuid)
 * @method null|User find($id, $lockMode = null, $lockVersion = null)
 * @method null|User findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends AbstractRepository
{
    public function findOneByUsername(string $username): ?User
    {
        $query = $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.username) = LOWER(:username)')->setParameter('username', trim($username), Types::STRING)
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
            ->andWhere('LOWER(u.email) = LOWER(:email)')->setParameter('email', $email, Types::STRING)
            ->getQuery();

        try {
            $result = $query->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            $results = $query->getResult();
            $result = array_shift($results);
        }

        return $result;
    }

    public function findOneByPhone(string $phone): ?User
    {
        $query = $this->createQueryBuilder('u')
            ->andWhere('u.phone = :phone')->setParameter('phone', $phone, Types::STRING)
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
            ->orWhere('LOWER(u.username) = LOWER(:username)')->setParameter('username', $identifier, Types::STRING)
            ->orWhere('LOWER(u.email) = LOWER(:email)')->setParameter('email', $identifier, Types::STRING)
            ->orWhere('u.phone = :phone')->setParameter('phone', $identifier, Types::STRING)
            ->getQuery();

        try {
            $result = $query->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            $results = $query->getResult();
            $result = array_shift($results);
        }

        return $result;
    }

    public function findOneByExternalId(string $external_id): ?User
    {
        $query = $this->createQueryBuilder('u')
            ->andWhere('u.external_id = :external_id')->setParameter('external_id', $external_id, Types::STRING)
            ->getQuery();

        try {
            $result = $query->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            $results = $query->getResult();
            $result = array_shift($results);
        }

        return $result;
    }

    public function findByFirstnameOrLastname($firstname, $lastname, $limit = 1000, $offset = 0): array
    {
        $query = $this->createQueryBuilder('u');

        if ($firstname || $lastname) {
            if ($firstname && !$lastname) {
                $lastname = $firstname;
            }
            if ($lastname && !$firstname) {
                $firstname = $lastname;
            }

            $query
                ->orWhere('u.firstname = :firstname1 OR u.firstname LIKE :firstname2')
                ->setParameter('firstname1', $firstname, Types::STRING)
                ->setParameter('firstname2', $firstname . '%', Types::STRING)
                ->orWhere('u.lastname = :lastname1 OR u.lastname LIKE :lastname2')
                ->setParameter('lastname1', $lastname, Types::STRING)
                ->setParameter('lastname2', $lastname . '%', Types::STRING)
                ->andWhere('u.status = :status')
                ->setParameter('status', \App\Domain\Types\UserStatusType::STATUS_WORK)
                ->setMaxResults($limit)
                ->setFirstResult($offset);

            return $query->getQuery()->getResult();
        }

        return [];
    }
}
