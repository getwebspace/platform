<?php

namespace App\Application\Middlewares;

use App\Domain\Exceptions\HttpBadRequestException;
use Slim\Http\Request;
use Slim\Http\Response;

class IsEnabledMiddleware extends Middleware
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param callable $next
     *
     * @return Response
     * @throws \Exception
     */
    public function __invoke(Request $request, Response $response, $next): \Slim\Http\Response
    {
        /** @var \Slim\Interfaces\RouteInterface $route */
        $route = $request->getAttribute('route');

        if ($this->getParameter($route->getName() . '_is_enabled', 'no') === 'yes') {
            return $next($request, $response);
        }

        throw new HttpBadRequestException($request, 'Module is not enabled');
    }
}
