<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;

class IsEnabledMiddleware extends AbstractMiddleware
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
        $routeName = explode(':', $route->getName())[1] ?? '';

        if ($routeName && $this->parameter($routeName . '_is_enabled', 'yes') !== 'no') {
            return $next($request, $response);
        }

        return $response
            ->withHeader('Location', str_start_with($route->getPattern(), '/cup') ? '/cup/forbidden' : '/forbidden')
            ->withStatus(307);
    }
}
