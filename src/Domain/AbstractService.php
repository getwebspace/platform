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

    protected function query(Model $model, array $criteria = [], array $data = [])
    {
        $query = $model->newQuery();

        foreach ($criteria as $key => $value) {
            if (is_array($value)) {
                $query->orWhereIn($key, $value);
            } else {
                $query->orWhere($key, $value);
            }
        }

        if (isset($data['order'])) {
            foreach ($data['order'] as $column => $direction) {
                $query = $query->orderBy($column, $direction);
            }
        }

        if (isset($data['limit'])) {
            $query = $query->limit($data['limit']);
        }

        if (isset($data['offset'])) {
            $query = $query->offset($data['offset']);
        }

        return $query->get();
    }

    abstract public function create(array $data = []);

    abstract public function read(array $data = []);

    abstract public function update($entity, array $data = []);

    abstract public function delete($entity);
}
