<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\ParameterTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Psr\Container\ContainerInterface;
use Illuminate\Database\Connection as DataBase;
use Symfony\Component\Cache\Adapter\ArrayAdapter as Cache;

abstract class AbstractService
{
    use ParameterTrait;

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
