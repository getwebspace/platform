<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\UserService;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;

class AuthorizationMiddleware extends AbstractMiddleware
{
    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        $data = [
            'uuid' => $request->getCookieParams()['uuid'] ?? null,
            'session' => $request->getCookieParams()['session'] ?? null,
            'agent' => $request->getServerParams()['HTTP_USER_AGENT'] ?? '',
            'ip' => $request->getServerParams()['HTTP_X_REAL_IP'] ??
                    $request->getServerParams()['HTTP_X_FORWARDED_FOR'] ??
                    $request->getServerParams()['REMOTE_ADDR'] ??
                    '',
        ];

        if ($data['uuid'] && \Ramsey\Uuid\Uuid::isValid((string) $data['uuid']) && $data['session']) {
            try {
                /** @var UserService $userService */
                $userService = $this->container->get(UserService::class);
                $user = $userService->read([
                    'uuid' => $data['uuid'],
                    'status' => \App\Domain\Types\UserStatusType::STATUS_WORK,
                ]);

                if ($user) {
                    $session = $user->getSession();

                    if ($session) {
                        $hash = $session->getHash();

                        if (
                            $data['session'] === $hash && (
                                $this->parameter('user_deep_check', 'no') === 'no'
                                || (
                                    $session->getAgent() === $data['agent']
                                    && $session->getIp() === $data['ip']
                                )
                            )
                        ) {
                            $request = $request->withAttribute('user', $user);

                            return $handler->handle($request);
                        }
                    }
                }

                throw new \RuntimeException();
            } catch (\RuntimeException|UserNotFoundException $e) {
                // clear cookie
                setcookie('uuid', '-1', time(), '/');
                setcookie('session', '-1', time(), '/');
            }
        }

        return $handler->handle($request);
    }
}
