<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\Entities\User;
use Slim\Http\Request;
use Slim\Http\Response;

class AccessCheckerMiddleware extends AbstractMiddleware
{
    public const PUBLIC = [
        'api:', // todo when API will be updated check this
        'common:',
        'cup:login',
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
        /** @var User $user */
        $user = $request->getAttribute('user', false);

        /** @var \Slim\Interfaces\RouteInterface $route */
        $route = $request->getAttribute('route');

        // continue in any way
        if (str_start_with($route->getName(), static::PUBLIC)) {
            return $next($request, $response);
        }

        if ($user) {
            // no group or access right
            if ($user->getGroup() === null || in_array($route->getName(), $user->getGroup()->getAccess(), true)) {
                return $next($request, $response);
            }

            return $response->withHeader('Location', '/cup/forbidden')->withStatus(307);
        }

        return $response->withHeader('Location', '/cup/login?redirect=' . $request->getUri()->getPath())->withStatus(307);
    }
}
