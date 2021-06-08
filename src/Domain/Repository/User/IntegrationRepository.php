<?php declare(strict_types=1);

namespace App\Domain\Repository\User;

use App\Domain\AbstractRepository;
use App\Domain\Entities\User\Integration as UserIntegration;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method null|UserIntegration findOneByUuid($uuid)
 * @method null|UserIntegration find($id, $lockMode = null, $lockVersion = null)
 * @method null|UserIntegration findOneBy(array $criteria, array $orderBy = null)
 * @method UserIntegration[]    findAll()
 * @method UserIntegration[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IntegrationRepository extends AbstractRepository
{
    public function findOneByProvider(string $provider): ?UserIntegration
    {
        $query = $this->createQueryBuilder('ui')
            ->andWhere('ui.provider = :provider')->setParameter('provider', $provider, Types::STRING)
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
