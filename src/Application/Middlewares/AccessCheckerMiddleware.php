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
        'api:',
        'cup:login',
        'cup:forbidden',
        'cup:system',
    ];

    /**
     * @throws \Exception
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

        $access = $this->parameter('user_access', false);
        $access = $access === false ? [] : explode(',', $access);
        if ($user && $user->getGroup()) {
            $access = array_unique(array_merge($access, $user->getGroup()->getAccess()));
        }

        if (
            ($access === [] && str_start_with($route->getName(), 'common'))
            || in_array($route->getName(), $access, true)
        ) {
            return $next($request, $response);
        }

        $redirect = '/forbidden';

        if (str_start_with($route->getPattern(), '/cup')) {
            $redirect = '/cup/forbidden';

            if (!$user) {
                $redirect = '/cup/login?redirect=' . $request->getUri()->getPath();
            }
        }

        return $response->withHeader('Location', $redirect)->withStatus(307);
    }
}
