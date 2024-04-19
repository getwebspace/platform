<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\ParameterTrait;
use Illuminate\Database\Connection as DataBase;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;
use Symfony\Component\Cache\Adapter\ArrayAdapter as Cache;

abstract class AbstractMiddleware
{
    use ParameterTrait;

    protected ContainerInterface $container;

    protected DataBase $db;

    protected Cache $cache;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->db = $container->get(DataBase::class);
        $this->cache = $container->get(Cache::class);
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
