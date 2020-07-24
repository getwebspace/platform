<?php declare(strict_types=1);

namespace App\Domain\Repository\Catalog;

use App\Domain\AbstractRepository;
use App\Domain\Entities\Catalog\Order;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|Order findOneByUuid($uuid)
 * @method null|Order find($id, $lockMode = null, $lockVersion = null)
 * @method null|Order findOneBy(array $criteria, array $orderBy = null)
 * @method Order[]    findAll()
 * @method Order[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderRepository extends AbstractRepository
{
    public function findOneByAddress(string $address): ?Order
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
}
