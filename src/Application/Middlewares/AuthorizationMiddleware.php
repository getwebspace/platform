<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\Entities\User;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\UserService;
use App\Domain\Traits\SecurityTrait;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;

class AuthorizationMiddleware extends AbstractMiddleware
{
    use SecurityTrait;

    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        $tokens = [
            'access_token' => $request->getCookieParams()['access_token'] ?? null,
            'refresh_token' => $request->getCookieParams()['refresh_token'] ?? null,
        ];

        if (!$tokens['access_token'] && $tokens['refresh_token']) {
            $tokens = $this->refreshTokenPair($tokens['refresh_token']);

            setcookie('access_token', $tokens['access_token'], time() + (\App\Domain\References\Date::MINUTE * 10), '/');
            setcookie('refresh_token', $tokens['refresh_token'], time() + \App\Domain\References\Date::MONTH, '/');
        }

        if (
            $tokens['access_token'] && (
                ($uuid = $this->decodeAccessToken($tokens['access_token']))
                && \Ramsey\Uuid\Uuid::isValid($uuid)
            )
        ) {
            $user = $this->getUser($uuid);

            if ($user) {
                $request = $request->withAttribute('user', $user);
            } else {
                setcookie('access_token', '-1', time(), '/');
            }
        }

        return $handler->handle($request);
    }

    protected function getUser($uuid): ?User
    {
        try {
            /** @var UserService $userService */
            $userService = $this->container->get(UserService::class);

            return $userService->read([
                'uuid' => $uuid,
                'status' => \App\Domain\Types\UserStatusType::STATUS_WORK,
            ]);

        } catch (UserNotFoundException $e) {
            return null;
        }
    }
}
