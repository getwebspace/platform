<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use Slim\Http\Request;
use Slim\Http\Response;

class IsEnabledMiddleware extends Middleware
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param callable $next
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $next): \Slim\Http\Response
    {
        /** @var \Slim\Interfaces\RouteInterface $route */
        $route = $request->getAttribute('route');
        $routeName = array_first(explode(':', $route->getName()));

        if ($this->getParameter($routeName . '_is_enabled', 'yes') !== 'no') {
            return $next($request, $response);
        }

        return $response->withAddedHeader('Location', '/')->withStatus(301);
    }
}
