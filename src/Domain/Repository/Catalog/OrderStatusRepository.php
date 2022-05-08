<?php declare(strict_types=1);

namespace App\Domain\Repository\Catalog;

use App\Domain\AbstractRepository;
use App\Domain\Entities\Catalog\OrderStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|OrderStatus findOneByUuid($uuid)
 * @method null|OrderStatus find($id, $lockMode = null, $lockVersion = null)
 * @method null|OrderStatus findOneBy(array $criteria, array $orderBy = null)
 * @method OrderStatus[]    findAll()
 * @method OrderStatus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderStatusRepository extends AbstractRepository
{
    public function findOneByTitle(string $title): ?OrderStatus
    {
        $query = $this->createQueryBuilder('os')
            ->andWhere('os.title = :title')->setParameter('title', $title, Types::STRING)
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
