<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Application\Actions\Cup\User\UserAction;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Traits\SecurityTrait;

class LoginPageAction extends UserAction
{
    use SecurityTrait;

    protected function action(): \Slim\Psr7\Response
    {
        $identifier = $this->parameter('user_login_type', 'username');

        if ($this->isPost()) {
            $data = [
                'identifier' => $this->getParam('identifier', ''),
                'password' => $this->getParam('password', ''),

                'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                'ip' => $this->getRequestRemoteIP(),

                'redirect' => $this->getParam('redirect'),
            ];

            if ($this->isRecaptchaChecked()) {
                try {
                    $user = $this->userService->read([
                        'identifier' => $data['identifier'],
                        'password' => $data['password'],
                        'agent' => $data['agent'],
                        'ip' => $data['ip'],
                        'status' => \App\Domain\Types\UserStatusType::STATUS_WORK,
                    ]);
                    $tokens = $this->getTokenPair($user, $data['ip'], $data['agent'], 'Login via CUP');

                    setcookie('access_token', $tokens['access_token'], time() + (\App\Domain\References\Date::MINUTE * 10), '/');
                    setcookie('refresh_token', $tokens['refresh_token'], time() + \App\Domain\References\Date::MONTH, '/');

                    $this->container->get(\App\Application\PubSub::class)->publish('cup:user:login', $user);

                    return $this->respondWithRedirect($data['redirect'] ?: '/cup');
                } catch (UserNotFoundException $exception) {
                    $this->addError($identifier, $exception->getMessage());
                } catch (WrongPasswordException $exception) {
                    $this->addError('password', $exception->getMessage());
                }
            } else {
                $this->addError('grecaptcha', 'EXCEPTION_WRONG_GRECAPTCHA');
            }
        }

        return $this->respondWithTemplate('cup/auth/login.twig', ['identifier' => $identifier]);
    }
}
