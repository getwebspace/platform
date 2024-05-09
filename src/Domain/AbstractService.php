<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\HasParameters;
use Illuminate\Database\Connection as DataBase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter as Cache;

abstract class AbstractService
{
    use HasParameters;

    protected ContainerInterface $container;

    protected DataBase $db;

    protected Cache $cache;

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
    }

    abstract public function create(array $data = []);

    abstract public function read(array $data = []);

    abstract public function update($entity, array $data = []);

    abstract public function delete($entity);
}
