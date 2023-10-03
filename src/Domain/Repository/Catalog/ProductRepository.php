<?php declare(strict_types=1);

namespace App\Domain\Repository\Catalog;

use App\Domain\AbstractRepository;
use App\Domain\Entities\Catalog\Product;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|Product findOneByUuid($uuid)
 * @method null|Product find($id, $lockMode = null, $lockVersion = null)
 * @method null|Product findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends AbstractRepository
{
    public function findOneByTitle(string $title): ?Product
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

    public function findOneByAddress(string $address): ?Product
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

    public function findOneUnique(string $category, string $address, array $dimension, string $external_id): ?Product
    {
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.category = :category')->setParameter('category', $category, Types::STRING)
            ->andWhere('c.address = :address')->setParameter('address', $address, Types::STRING)
            ->andWhere('c.dimension = :dimension')->setParameter('dimension', $dimension, Types::JSON)
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

    public function findByTitle($title = null, $limit = 1000, $offset = 0): array
    {
        $query = $this->createQueryBuilder('c');

        if ($title) {
            $query
                ->where('c.title = :title1 OR c.title LIKE :title2')
                ->setParameter('title1', $title, Types::STRING)
                ->setParameter('title2', $title . '%', Types::STRING)
                ->andWhere('c.status = :status')
                ->setParameter('status', \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK)
                ->setMaxResults($limit)
                ->setFirstResult($offset);

            return $query->getQuery()->getResult();
        }

        return [];
    }
}
