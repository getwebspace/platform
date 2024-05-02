<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Traits\UseSecurity;

class LoginAction extends AuthAction
{
    use UseSecurity;

    protected function action(): \Slim\Psr7\Response
    {
        $identifier = $this->getParam('identifier', '');
        $password = $this->getParam('password', '');

        try {
            $user = $this->userService->read([
                'identifier' => $identifier,
                'password' => $password,
                'status' => \App\Domain\Casts\User\Status::WORK,
            ]);

            $tokens = $this->getTokenPair([
                'user' => $user,
                'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                'ip' => $this->getRequestRemoteIP(),
                'comment' => 'Login via Auth',
            ]);

            $this->container->get(\App\Application\PubSub::class)->publish('auth:user:login', $user);

            return $this->respondWithJson([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
            ]);
        } catch (UserNotFoundException|WrongPasswordException $exception) {
            return $this->response->withStatus(404);
        }
    }
}
