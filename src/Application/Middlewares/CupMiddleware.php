<?php

namespace App\Application\Middlewares;

use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

class CupMiddleware extends Middleware
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
        $user = $request->getAttribute('user', false);

        if ($user === false || !in_array($user->level, \App\Domain\Types\UserLevelType::CUP_ACCESS)) {
            return $response->withHeader('Location', '/cup/login?redirect=' . $request->getUri()->getPath())->withStatus(301);
        }
        if ($request->isPost() && $user && $user->level === \App\Domain\Types\UserLevelType::LEVEL_DEMO) {
            return $response->withHeader('Location', $request->getUri()->getPath())->withStatus(301);
        }

        return $next($request, $response);
    }
}
