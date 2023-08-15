<?php declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\AbstractRepository;
use App\Domain\Entities\Reference;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|Reference findOneByUuid($uuid)
 * @method null|Reference find($id, $lockMode = null, $lockVersion = null)
 * @method null|Reference findOneBy(array $criteria, array $orderBy = null)
 * @method Reference[]    findAll()
 * @method Reference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReferenceRepository extends AbstractRepository
{
    public function findOneByTitle(string $title): ?Reference
    {
        $query = $this->createQueryBuilder('r')
            ->andWhere('r.title = :title')->setParameter('title', $title, Types::STRING)
            ->getQuery();

        try {
            $result = $query->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            $results = $query->getResult();
            $result = array_shift($results);
        }

        return $result;
    }

    public function findByType(string $type): ?Reference
    {
        $query = $this->createQueryBuilder('r')
            ->andWhere('r.type = :type')->setParameter('type', $type, Types::STRING)
            ->getQuery();

        try {
            $result = $query->getResult();
        } catch (NonUniqueResultException $e) {
            $results = $query->getResult();
            $result = array_shift($results);
        }

        return $result;
    }
}
