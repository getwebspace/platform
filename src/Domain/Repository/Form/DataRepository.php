<?php declare(strict_types=1);

namespace App\Domain\Repository\Form;

use App\Domain\AbstractRepository;
use App\Domain\Entities\Form\Data as FormData;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|FormData findOneByUuid($uuid)
 * @method null|FormData find($id, $lockMode = null, $lockVersion = null)
 * @method null|FormData findOneBy(array $criteria, array $orderBy = null)
 * @method FormData[]    findAll()
 * @method FormData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DataRepository extends AbstractRepository
{
    public function findByFormUuid($uuid): ?FormData
    {
        $query = $this->createQueryBuilder('fd')
            ->andWhere('fd.form_uuid = :form_uuid')->setParameter('form_uuid', $uuid, \Ramsey\Uuid\Doctrine\UuidType::NAME)
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
