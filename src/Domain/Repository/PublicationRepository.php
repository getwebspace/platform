<?php declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\AbstractRepository;
use App\Domain\Entities\Publication;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|Publication findOneByUuid($uuid)
 * @method null|Publication find($id, $lockMode = null, $lockVersion = null)
 * @method null|Publication findOneBy(array $criteria, array $orderBy = null)
 * @method Publication[]    findAll()
 * @method Publication[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PublicationRepository extends AbstractRepository
{
    public function findByCategoryUuid($uuid)
    {
        if (\Ramsey\Uuid\Uuid::isValid((string) $uuid)) {
            $query = $this->createQueryBuilder('p')
                ->andWhere('p.category_uuid = :category')->setParameter('category', (string) $uuid, \Ramsey\Uuid\Doctrine\UuidType::NAME)
                ->getQuery();

            return $query->getArrayResult();
        }

        return null;
    }

    public function findOneByTitle(string $title): ?Publication
    {
        $query = $this->createQueryBuilder('p')
            ->andWhere('p.title = :title')->setParameter('title', $title, Types::STRING)
            ->getQuery();

        try {
            $result = $query->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            $results = $query->getResult();
            $result = array_shift($results);
        }

        return $result;
    }

    public function findOneByAddress(string $address): ?Publication
    {
        $query = $this->createQueryBuilder('p')
            ->andWhere('p.address = :address')->setParameter('address', $address, Types::STRING)
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
