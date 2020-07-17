<?php declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\AbstractRepository;
use App\Domain\Entities\Task;
use App\Domain\Entities\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|Task findOneByUuid($uuid)
 * @method null|Task find($id, $lockMode = null, $lockVersion = null)
 * @method null|Task findOneBy(array $criteria, array $orderBy = null)
 * @method Task[]    findAll()
 * @method Task[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TaskRepository extends AbstractRepository
{
}
