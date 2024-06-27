<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\Models\User;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

class AccessCheckerMiddleware extends AbstractMiddleware
{
    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        // skip check if is API
        if (str_starts_with($route->getName(), 'api:') || str_starts_with($route->getName(), 'auth:oauth')) {
            return $handler->handle($request);
        }

        /** @var User $user */
        $user = $request->getAttribute('user', false);

        $access = $this->parameter('user_access', false);
        $access = $access === false ? [] : explode(',', $access);
        if ($user && $user->group) {
            $access = array_unique(array_merge($access, $user->group->access));
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
