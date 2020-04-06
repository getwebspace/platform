<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use Slim\Http\Request;
use Slim\Http\Response;

class CupMiddleware extends Middleware
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
        $user = $request->getAttribute('user', false);

        if ($user === false || !in_array($user->level, \App\Domain\Types\UserLevelType::CUP_ACCESS, true)) {
            return $response->withHeader('Location', '/cup/login?redirect=' . $request->getUri()->getPath())->withStatus(301);
        }
        if ($request->isPost() && $user && $user->level === \App\Domain\Types\UserLevelType::LEVEL_DEMO) {
            return $response->withHeader('Location', $request->getUri()->getPath())->withStatus(301);
        }

        return $next($request, $response);
    }
}
