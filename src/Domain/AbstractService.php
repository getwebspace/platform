<?php declare(strict_types=1);

namespace App\Domain;

use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractService extends AbstractComponent
{
    /**
     * @var AbstractRepository
     */
    protected $service;

    protected static array $default_read = [
        'order' => [],
        'limit' => null,
        'offset' => null,
    ];

    public function __construct(ContainerInterface $container = null, EntityManager $entityManager = null, LoggerInterface $logger = null)
    {
        parent::__construct($container, $entityManager, $logger);

        $this->init();
    }

    public static function getWithContainer(ContainerInterface $container)
    {
        return new static($container);
    }

    public static function getWithEntityManager(EntityManager $entityManager, LoggerInterface $logger = null)
    {
        return new static(null, $entityManager, $logger);
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
