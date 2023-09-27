<?php declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\AbstractRepository;
use App\Domain\Entities\GuestBook;

/**
 * @method null|GuestBook findOneByUuid($uuid)
 * @method null|GuestBook find($id, $lockMode = null, $lockVersion = null)
 * @method null|GuestBook findOneBy(array $criteria, array $orderBy = null)
 * @method GuestBook[]    findAll()
 * @method GuestBook[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GuestBookRepository extends AbstractRepository {}
