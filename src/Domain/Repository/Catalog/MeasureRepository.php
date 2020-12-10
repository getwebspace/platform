<?php declare(strict_types=1);

namespace App\Domain\Repository\Catalog;

use App\Domain\AbstractRepository;
use App\Domain\Entities\Catalog\Measure;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|Measure findOneByUuid($uuid)
 * @method null|Measure find($id, $lockMode = null, $lockVersion = null)
 * @method null|Measure findOneBy(array $criteria, array $orderBy = null)
 * @method Measure[]    findAll()
 * @method Measure[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MeasureRepository extends AbstractRepository
{
    public function findOneByTitle(string $title): ?Measure
    {
        $query = $this->createQueryBuilder('m')
            ->andWhere('m.title = :title')->setParameter('title', $title, Types::STRING)
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
