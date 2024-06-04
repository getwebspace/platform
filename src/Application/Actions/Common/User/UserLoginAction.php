<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;

class UserLoginAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $identifier = $this->parameter('user_login_type', 'username');
        $provider = $this->getParam('provider', $_SESSION['auth_provider'] ?? 'BasicAuthProvider');

        $data = [
            'username' => $this->getParam('username'),
            'phone' => $this->getParam('phone'),
            'email' => $this->getParam('email'),
            'password' => $this->getParam('password'),
            'code' => $this->getParam('code'),
            'state' => $this->getParam('state'),
        ];

        if (
            ($this->isGet() && $data['code'] && $data['state'])
            || ($this->isPost() && (($data[$identifier] && $data['password']) || $provider))
        ) {
            if ($this->isPost() && $this->isRecaptchaChecked() || $this->isGet()) {
                try {
                    $result = $this->auth->login(
                        $provider,
                        [
                            $identifier => $data[$identifier],
                            'password' => $this->getParam('password'),
                            'code' => $this->getParam('code'),
                            'state' => $this->getParam('state'),
                        ],
                        [
                            'redirect' => $this->request->getUri()->getPath(),
                            'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                            'ip' => $this->getRequestRemoteIP(),
                            'comment' => 'Login via common page',
                        ]
                    );

                    @setcookie('access_token', $result['access_token'], time() + \App\Domain\References\Date::MONTH, '/');
                    @setcookie('refresh_token', $result['refresh_token'], time() + \App\Domain\References\Date::MONTH, '/auth');

                    return $this->respondWithRedirect($this->getParam('redirect', '/user/profile'));
                } catch (UserNotFoundException $e) {
                    $this->addError($identifier, $e->getMessage());
                } catch (WrongPasswordException $e) {
                    $this->addError('password', $e->getMessage());
                }
            } else {
                $this->addError('grecaptcha', 'EXCEPTION_WRONG_GRECAPTCHA');
            }
        }

        return $this->respond($this->parameter('user_login_template', 'user.login.twig'), [
            'identifier' => $identifier,
            'oauth' => $this->container->get('plugin')->get()->filter(function ($plugin) {
                return is_a($plugin, \App\Domain\Plugin\AbstractOAuthPlugin::class);
            }),
        ]);
    }
}
