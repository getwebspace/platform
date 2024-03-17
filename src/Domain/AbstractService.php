<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\ParameterTrait;
use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Illuminate\Database\Connection as DataBase;

abstract class AbstractService
{
    use ParameterTrait;

    protected ContainerInterface $container;

    protected EntityManager $entityManager;

    protected DataBase $db;

    /**
     * @var AbstractRepository
     */
    protected mixed $service;

    protected static array $default_read = [
        'order' => [],
        'limit' => null,
        'offset' => null,
    ];

    public function __construct(ContainerInterface $container, EntityManager $entityManager, DataBase $db)
    {
        $this->container = $container; // deprecated
        $this->entityManager = $entityManager; // deprecated
        $this->db = $db;

        $this->init(); // deprecated
    }

    abstract protected function init(); // deprecated

    /**
     * @deprecated
     * @param null|string $indexBy the index for the from
     */
    public function createQueryBuilder(string $alias, ?string $indexBy = null): \Doctrine\ORM\QueryBuilder
    {
        throw new \RuntimeException('Deprecated method');
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function query(string $sql): \Doctrine\DBAL\Statement
    {
        throw new \RuntimeException('Deprecated method');
    }

    abstract public function create(array $data = []);

    abstract public function read(array $data = []);

    /**
     * @deprecated
     */
    public function count(array $criteria = []): int
    {
        return $this->service->count($criteria);
    }

    abstract public function update($entity, array $data = []);

    abstract public function delete($entity);

    /**
     * @deprecated
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function write(AbstractEntity $entity): AbstractEntity
    {
        throw new \RuntimeException('Deprecated method');
    }
}
