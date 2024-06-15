<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\HasParameters;
use Illuminate\Database\Connection as DataBase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter as Cache;
use Symfony\Component\Cache\Adapter\FilesystemAdapter as FileCache;
use Twig\Extension\ExtensionInterface;

abstract class AbstractExtension implements ExtensionInterface
{
    use HasParameters;

    protected ContainerInterface $container;

    protected DataBase $db;

    protected Cache $cache;

    protected FileCache $persistentCache;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->db = $container->get(DataBase::class);
        $this->cache = $container->get(Cache::class);
        $this->persistentCache = $container->get(FileCache::class);
    }

    public function getTokenParsers()
    {
        return [];
    }

    public function getNodeVisitors()
    {
        return [];
    }

    public function getFilters()
    {
        return [];
    }

    public function getTests()
    {
        return [];
    }

    public function getFunctions()
    {
        return [];
    }

    public function getOperators()
    {
        return [];
    }
}
