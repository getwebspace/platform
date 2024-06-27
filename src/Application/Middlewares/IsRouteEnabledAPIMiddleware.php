<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

class IsRouteEnabledAPIMiddleware extends AbstractMiddleware
{
    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $routeName = explode(':', $route->getName())[2] ?? '';

        if ($routeName && $this->parameter($routeName . '_is_enabled', 'yes') !== 'no') {
            return $handler->handle($request);
        }

        $response = new Response();
        $response->getBody()->write('Access denied');

        return $response
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withStatus(423);
    }
}
