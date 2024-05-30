<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Models\User;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Traits\UseSecurity;

class UserLoginAction extends UserAction
{
    use UseSecurity;

    protected function action(): \Slim\Psr7\Response
    {
        $identifier = $this->parameter('user_login_type', 'username');

        if ($this->isPost()) {
            $data = [
                'phone' => $this->getParam('phone', ''),
                'email' => $this->getParam('email', ''),
                'username' => $this->getParam('username', ''),
                'password' => $this->getParam('password', ''),
            ];

            if ($this->isRecaptchaChecked()) {
                try {
                    $user = $this->userService->read([
                        $identifier => $data[$identifier],
                        'password' => $data['password'],
                    ]);

                    if ($user) {
                        $tokens = $this->getTokenPair([
                            'user' => $user,
                            'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                            'ip' => $this->getRequestRemoteIP(),
                            'comment' => 'Login via common page',
                        ]);

                        setcookie('access_token', $tokens['access_token'], time() + \App\Domain\References\Date::MONTH, '/');
                        setcookie('refresh_token', $tokens['refresh_token'], time() + \App\Domain\References\Date::MONTH, '/auth');

                        $this->container->get(\App\Application\PubSub::class)->publish('common:user:login', $user);

                        return $this->respondWithRedirect($this->getParam('redirect', '/user/profile'));
                    }
                } catch (UserNotFoundException $e) {
                    $this->addError($identifier, $e->getMessage());
                } catch (WrongPasswordException $e) {
                    $this->addError('password', $e->getMessage());
                }
            }

            $this->addError('grecaptcha', 'EXCEPTION_WRONG_GRECAPTCHA');
        }

        return $this->respond($this->parameter('user_login_template', 'user.login.twig'), [
            'identifier' => $identifier,
            'oauth' => $this->container->get('plugin')->get()->filter(function ($plugin) {
                return is_a($plugin, \App\Domain\Plugin\AbstractOAuthPlugin::class);
            })
        ]);
    }
}
