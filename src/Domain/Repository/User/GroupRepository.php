<?php declare(strict_types=1);

namespace App\Domain\Repository\User;

use App\Domain\AbstractRepository;
use App\Domain\Entities\User\Group as UserGroup;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|UserGroup findOneByUuid($uuid)
 * @method null|UserGroup find($id, $lockMode = null, $lockVersion = null)
 * @method null|UserGroup findOneBy(array $criteria, array $orderBy = null)
 * @method UserGroup[]    findAll()
 * @method UserGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupRepository extends AbstractRepository
{
    public function findOneByTitle(string $title): ?UserGroup
    {
        $query = $this->createQueryBuilder('ug')
            ->andWhere('ug.title = :title')->setParameter('title', $title, Types::STRING)
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
