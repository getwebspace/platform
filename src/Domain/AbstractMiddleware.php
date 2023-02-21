<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\ParameterTrait;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;

abstract class AbstractMiddleware
{
    use ParameterTrait;

    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
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
