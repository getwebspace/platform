<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

class IsRouteEnabledMiddleware extends AbstractMiddleware
{
    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $routeName = explode(':', $route->getName())[1] ?? '';

        if ($routeName && $this->parameter($routeName . '_is_enabled', 'yes') !== 'no') {
            return $handler->handle($request);
        }

        return (new Response())
            ->withHeader('Location', str_starts_with($route->getPattern(), '/cup') ? '/cup/forbidden' : '/forbidden')
            ->withStatus(307);
    }
}
