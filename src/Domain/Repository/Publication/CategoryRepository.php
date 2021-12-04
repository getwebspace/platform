<?php declare(strict_types=1);

namespace App\Domain\Repository\Publication;

use App\Domain\AbstractRepository;
use App\Domain\Entities\Publication\Category as PublicationCategory;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|PublicationCategory findOneByUuid($uuid)
 * @method null|PublicationCategory find($id, $lockMode = null, $lockVersion = null)
 * @method null|PublicationCategory findOneBy(array $criteria, array $orderBy = null)
 * @method PublicationCategory[]    findAll()
 * @method PublicationCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryRepository extends AbstractRepository
{
    public function findOneByParentUuid($uuid)
    {
        if (\Ramsey\Uuid\Uuid::isValid((string) $uuid)) {
            $query = $this->createQueryBuilder('pc')
                ->andWhere('pc.parent = :parent')->setParameter('parent', (string) $uuid, \Ramsey\Uuid\Doctrine\UuidType::NAME)
                ->getQuery();

            return $query->getArrayResult();
        }

        return null;
    }

    public function findOneByTitle(string $title): ?PublicationCategory
    {
        $query = $this->createQueryBuilder('pc')
            ->andWhere('pc.title = :title')->setParameter('title', $title, Types::STRING)
            ->getQuery();

        try {
            $result = $query->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            $results = $query->getResult();
            $result = array_shift($results);
        }

        return $result;
    }

    public function findOneByAddress(string $address): ?PublicationCategory
    {
        $query = $this->createQueryBuilder('pc')
            ->andWhere('pc.address = :address')->setParameter('address', $address, Types::STRING)
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
