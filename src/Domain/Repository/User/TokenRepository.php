<?php

namespace App\Domain\Repository\User;

use App\Domain\AbstractRepository;
use App\Domain\Entities\User\Token as UserToken;

/**
 * @method null|UserToken findOneByUuid($uuid)
 * @method null|UserToken find($id, $lockMode = null, $lockVersion = null)
 * @method null|UserToken findOneBy(array $criteria, array $orderBy = null)
 * @method UserToken[]    findAll()
 * @method UserToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TokenRepository extends AbstractRepository
{
}
