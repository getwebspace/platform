<?php declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\AbstractRepository;
use App\Domain\Entities\FileRelation;

/**
 * @method null|FileRelation findOneByUuid($uuid)
 * @method null|FileRelation find($id, $lockMode = null, $lockVersion = null)
 * @method null|FileRelation findOneBy(array $criteria, array $orderBy = null)
 * @method FileRelation[]    findAll()
 * @method FileRelation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FileRelationRepository extends AbstractRepository {}
