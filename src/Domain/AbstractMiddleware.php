<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\HasParameters;
use Illuminate\Cache\ArrayStore as ArrayCache;
use Illuminate\Cache\FileStore as FileCache;
use Illuminate\Database\Connection as DataBase;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;

abstract class AbstractMiddleware
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

    abstract public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response;

    protected function getRequestRemoteIP(Request $request): string
    {
        return
            $request->getServerParams()['HTTP_X_REAL_IP'] ??
            $request->getServerParams()['HTTP_X_FORWARDED_FOR'] ??
            $request->getServerParams()['REMOTE_ADDR'] ??
            '';
    }
}
