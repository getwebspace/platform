<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\Entities\User;
use Slim\Http\Request;
use Slim\Http\Response;

class AccessCheckerMiddleware extends AbstractMiddleware
{
    public const PUBLIC = [
        'forbidden',
        'api:cml',
        'api:entity',
        'cup:login',
        'cup:forbidden',
        'cup:system',
    ];

    /**
     * @param Request  $request
     * @param Response $response
     * @param callable $next
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function __invoke(Request $request, Response $response, callable $next): \Slim\Http\Response
    {
        /** @var \Slim\Interfaces\RouteInterface $route */
        $route = $request->getAttribute('route');

        // continue in any way
        if (str_start_with($route->getName(), static::PUBLIC)) {
            return $next($request, $response);
        }

        /** @var User $user */
        $user = $request->getAttribute('user', false);

        if ($user && $user->getGroup()) {
            $access = $user->getGroup()->getAccess();
        } else {
            $access = $this->parameter('user_access', false);
            $access = $access === false ? [] : explode(',', $access);
        }

        if (
            ($access === [] && str_start_with($route->getName(), 'common')) ||
            in_array($route->getName(), $access, true)
        ) {
            return $next($request, $response);
        }

        return $response
            ->withHeader('Location', str_start_with($route->getPattern(), '/cup') ? '/cup/forbidden' : '/forbidden')
            ->withStatus(307);
    }
}
