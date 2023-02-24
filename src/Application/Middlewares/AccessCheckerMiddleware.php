<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\Entities\User;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

class AccessCheckerMiddleware extends AbstractMiddleware
{
    public const PUBLIC = [
        'api:',
        'auth:',
        'common:forbidden',
        'cup:login',
        'cup:forbidden',
        'cup:system',
    ];

    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        // continue in any way
        foreach (static::PUBLIC as $r) {
            if (str_starts_with($route->getName(), $r)) {
                return $handler->handle($request);
            }
        }

        /** @var User $user */
        $user = $request->getAttribute('user', false);

        $access = $this->parameter('user_access', false);
        $access = $access === false ? [] : explode(',', $access);
        if ($user && $user->getGroup()) {
            $access = array_unique(array_merge($access, $user->getGroup()->getAccess()));
        }

        if (
            ($access === [] && str_starts_with($route->getName(), 'common'))
            || in_array($route->getName(), $access, true)
        ) {
            return $handler->handle($request);
        }

        $redirect = '/forbidden';

        if (str_starts_with($route->getPattern(), '/cup')) {
            $redirect = '/cup/forbidden';

            if (!$user) {
                $redirect = '/cup/login?redirect=' . $request->getUri()->getPath();
            }
        }

        return (new Response())
            ->withHeader('Location', $redirect)
            ->withStatus(307);
    }
}
