<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\Entities\User;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\UserService;
use App\Domain\Traits\SecurityTrait;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
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
        $path = $request->getUri()->getPath();
        $access_token = $request->getCookieParams()['access_token'] ?? null;

        if ($path !== '/auth/refresh-token') {
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
                        }
                    }
                } catch (ExpiredException|SignatureInvalidException $e) {
                    return (new Response())
                        ->withHeader('Location', '/auth/refresh-token?redirect=' . $request->getUri()->getPath())
                        ->withStatus(308);
                }
            }
        }

        return $handler->handle($request);
    }

}
