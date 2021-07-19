<?php declare(strict_types=1);

namespace App\Domain\Repository\Catalog;

use App\Domain\AbstractRepository;
use App\Domain\Entities\Catalog\Category;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|Category findOneByUuid($uuid)
 * @method null|Category find($id, $lockMode = null, $lockVersion = null)
 * @method null|Category findOneBy(array $criteria, array $orderBy = null)
 * @method Category[]    findAll()
 * @method Category[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryRepository extends AbstractRepository
{
    public function findOneByTitle(string $title): ?Category
    {
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.title = :title')->setParameter('title', $title, Types::STRING)
            ->getQuery();

        try {
            $result = $query->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            $results = $query->getResult();
            $result = array_shift($results);
        }

        return $result;
    }

    public function findOneByAddress(string $address): ?Category
    {
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.address = :address')->setParameter('address', $address, Types::STRING)
            ->getQuery();

        try {
            $result = $query->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            $results = $query->getResult();
            $result = array_shift($results);
        }

        return $result;
    }

    public function findOneUnique(string $parent, string $address, string $external_id): ?Category
    {
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.parent = :parent')->setParameter('parent', $parent, Types::STRING)
            ->andWhere('c.address = :address')->setParameter('address', $address, Types::STRING)
            ->andWhere('c.external_id = :external_id')->setParameter('external_id', $external_id, Types::STRING)
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
