<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\Entities\User;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\UserService;
use App\Domain\Traits\SecurityTrait;
use Firebase\JWT\ExpiredException;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class AuthorizationMiddleware extends AbstractMiddleware
{
    use SecurityTrait;

    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        // skip if refresh token
        if ($request->getUri()->getPath() === '/auth/refresh-token') {
            return $handler->handle($request);
        }

        $access_token = $request->getCookieParams()['access_token'] ?? null;

        if ($access_token) {
            try {
                $uuid = $this->getUUIDFromAccessToken($access_token);

                if ($uuid && \Ramsey\Uuid\Uuid::isValid($uuid)) {
                    try {
                        /** @var UserService $userService */
                        $userService = $this->container->get(UserService::class);

                        /** @var User $user */
                        $user = $userService->read([
                            'uuid' => $uuid,
                            'status' => \App\Domain\Types\UserStatusType::STATUS_WORK,
                        ]);

                        $request = $request->withAttribute('user', $user);
                    } catch (UserNotFoundException $e) {
                        return (new Response())
                            ->withHeader('Location', '/auth/logout')
                            ->withStatus(307);
                    }
                }
            } catch (ExpiredException $e) {
                return (new Response())
                    ->withHeader('Location', '/auth/refresh-token?redirect=' . $request->getUri()->getPath())
                    ->withStatus(308);
            }
        }

        return $handler->handle($request);
    }
}
