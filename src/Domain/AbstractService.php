<?php declare(strict_types=1);

namespace App\Domain;

use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;

abstract class AbstractService
{
    protected ContainerInterface $container;

    protected EntityManager $entityManager;

    /**
     * @var AbstractRepository
     */
    protected mixed $service;

    protected static array $default_read = [
        'order' => [],
        'limit' => null,
        'offset' => null,
    ];

    public function __construct(ContainerInterface $container, EntityManager $entityManager)
    {
        $this->container = $container;
        $this->entityManager = $entityManager;

        $this->init();
    }

    abstract protected function init();

    /**
     * @param string $alias
     * @param string $indexBy the index for the from
     */
    public function createQueryBuilder($alias, $indexBy = null): \Doctrine\ORM\QueryBuilder
    {
        return $this->service->createQueryBuilder($alias, $indexBy);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function query(string $sql): \Doctrine\DBAL\Statement
    {
        return $this->entityManager->getConnection()->prepare($sql);
    }

    abstract public function create(array $data = []);

    abstract public function read(array $data = []);

    public function count(array $criteria = []): int
    {
        return $this->service->count($criteria);
    }

    abstract public function update($entity, array $data = []);

    abstract public function delete($entity);

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function write(AbstractEntity $entity): AbstractEntity
    {
        $this->entityManager->flush($entity);

        return $entity;
    }
}
