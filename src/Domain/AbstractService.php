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

    /**
     * @var array
     */
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
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createQueryBuilder($alias, $indexBy = null)
    {
        return $this->service->createQueryBuilder($alias, $indexBy);
    }

    abstract public function create(array $data = []);

    abstract public function read(array $data = []);

    public function count(array $criteria = [])
    {
        return $this->service->count($criteria);
    }

    abstract public function update($entity, array $data = []);

    abstract public function delete($entity);
}
