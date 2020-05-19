<?php declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\AbstractRepository;
use App\Domain\Entities\File;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|File findOneByUuid($uuid)
 * @method null|File find($id, $lockMode = null, $lockVersion = null)
 * @method null|File findOneBy(array $criteria, array $orderBy = null)
 * @method File[]    findAll()
 * @method File[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FileRepository extends AbstractRepository
{
    public function findOneByHash(string $hash): ?File
    {
        $query = $this->createQueryBuilder('f')
            ->andWhere('f.hash = :hash')->setParameter('hash', $hash, Types::STRING)
            ->getQuery();

        try {
            $result = $query->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            $results = $query->getResult();
            $result = array_shift($results);
        }

        return $result;
    }

    public function findByExt(string $ext)
    {
        $query = $this->createQueryBuilder('p')
            ->andWhere('p.ext = :ext')->setParameter('ext', $ext, Types::STRING)
            ->getQuery();

        return $query->getArrayResult();
    }
}
