<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\ParameterTrait;
use Psr\Container\ContainerInterface;
use Illuminate\Database\Connection as DataBase;
use Symfony\Component\Cache\Adapter\ArrayAdapter as Cache;

abstract class AbstractService
{
    use ParameterTrait;

    protected ContainerInterface $container;

    protected DataBase $db;

    protected Cache $cache;

    /**
     * @var AbstractRepository
     */
    protected mixed $service;

    protected static array $default_read = [
        'order' => [],
        'limit' => null,
        'offset' => null,
    ];

    public function __construct(ContainerInterface $container, DataBase $db, Cache $cache)
    {
        $this->container = $container;
        $this->db = $db;
        $this->cache = $cache;

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
