<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\HasParameters;
use Illuminate\Database\Connection as DataBase;
use Illuminate\Cache\ArrayStore as ArrayCache;
use Illuminate\Cache\FileStore as FileCache;
use Psr\Container\ContainerInterface;

abstract class AbstractService
{
    use HasParameters;

    protected ContainerInterface $container;

    protected DataBase $db;

    protected ArrayCache $arrayCache;

    protected FileCache $fileCache;

    protected static array $default_read = [
        'order' => [],
        'limit' => null,
        'offset' => null,
    ];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->db = $container->get(DataBase::class);
        $this->arrayCache = $container->get(ArrayCache::class);
        $this->fileCache = $container->get(FileCache::class);
    }

    abstract public function create(array $data = []);

    abstract public function read(array $data = []);

    abstract public function update($entity, array $data = []);

    abstract public function delete($entity);
}
