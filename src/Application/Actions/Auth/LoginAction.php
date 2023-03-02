<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Traits\SecurityTrait;

class LoginAction extends AuthAction
{
    use SecurityTrait;

    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            $identifier = $this->getParam('identifier', '');
            $password = $this->getParam('password', '');

            $data = [
                'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                'ip' => $this->getRequestRemoteIP(),
            ];

            try {
                $user = $this->userService->read([
                    'identifier' => $identifier,
                    'password' => $password,
                    'agent' => $data['agent'],
                    'ip' => $data['ip'],
                    'status' => \App\Domain\Types\UserStatusType::STATUS_WORK,
                ]);
                $tokens = $this->getTokenPair($user, $data['ip'], $data['agent'], 'Login via Auth');
                $this->container->get(\App\Application\PubSub::class)->publish('auth:user:login', $user);

                return $this
                    ->respondWithJson([
                        'access_token' => $tokens['access_token'],
                        'refresh_token' => $tokens['refresh_token'],
                    ]);
            } catch (UserNotFoundException|WrongPasswordException $exception) {
            }
        }

        return $this->response->withStatus(401);
    }
}
