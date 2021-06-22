<?php declare(strict_types=1);

namespace App\Domain\Repository\User;

use App\Domain\AbstractRepository;
use App\Domain\Entities\User\Session as UserSession;

/**
 * @method null|UserSession findOneByUuid($uuid)
 * @method null|UserSession find($id, $lockMode = null, $lockVersion = null)
 * @method null|UserSession findOneBy(array $criteria, array $orderBy = null)
 * @method UserSession[]    findAll()
 * @method UserSession[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SessionRepository extends AbstractRepository
{
}
