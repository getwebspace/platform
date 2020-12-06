<?php declare(strict_types=1);

namespace App\Domain\Repository\User;

use App\Domain\AbstractRepository;
use App\Domain\Entities\User\Subscriber as UserSubscriber;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|UserSubscriber findOneByUuid($uuid)
 * @method null|UserSubscriber find($id, $lockMode = null, $lockVersion = null)
 * @method null|UserSubscriber findOneBy(array $criteria, array $orderBy = null)
 * @method UserSubscriber[]    findAll()
 * @method UserSubscriber[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SubscriberRepository extends AbstractRepository
{
    public function findOneByEmail(string $email): ?UserSubscriber
    {
        $query = $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')->setParameter('email', $email, Types::STRING)
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
