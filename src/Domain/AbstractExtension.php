<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\HasParameters;
use Illuminate\Cache\ArrayStore as ArrayCache;
use Illuminate\Cache\FileStore as FileCache;
use Illuminate\Database\Connection as DataBase;
use Psr\Container\ContainerInterface;
use Twig\Extension\ExtensionInterface;

abstract class AbstractExtension implements ExtensionInterface
{
    use HasParameters;

    protected ContainerInterface $container;

    protected DataBase $db;

    protected ArrayCache $arrayCache;

    protected FileCache $fileCache;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->db = $container->get(DataBase::class);
        $this->arrayCache = $container->get(ArrayCache::class);
        $this->fileCache = $container->get(FileCache::class);
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

    /**
     * Must be exactly [unary, binary] - twig validates the shape since 3.21
     * and rejects a plain empty array, which every template render then
     * reports as a compile error rather than anything mentioning operators
     */
    public function getOperators()
    {
        return [[], []];
    }
}
