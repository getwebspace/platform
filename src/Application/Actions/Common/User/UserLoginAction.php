<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Service\User\Exception\UserNotConfirmedException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Traits\UseThrottle;

class UserLoginAction extends UserAction
{
    use UseThrottle;

    private const THROTTLE_SCOPE = 'user-login';

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
            $login = (string) ($data[$identifier] ?? '');
            $ip = $this->getRequestRemoteIP();

            if ($this->isPost() && $this->isThrottled(self::THROTTLE_SCOPE, $login, $ip)) {
                $this->addError($identifier, 'EXCEPTION_TOO_MANY_ATTEMPTS');
            } elseif ($this->isPost() && $this->isRecaptchaChecked() || $this->isGet()) {
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

                    $this->throttleClear(self::THROTTLE_SCOPE, $login, $ip);
                    $this->setAuthCookies($result);

                    return $this->respondWithRedirect($this->getRedirectParam('redirect', '/user/profile'));
                } catch (UserNotConfirmedException $e) {
                    // the credentials were fine, so this is not a failed attempt
                    $this->addError($identifier, $e->getMessage());
                } catch (UserNotFoundException $e) {
                    $this->throttleHit(self::THROTTLE_SCOPE, $login, $ip);
                    $this->addError($identifier, $e->getMessage());
                } catch (WrongPasswordException $e) {
                    $this->throttleHit(self::THROTTLE_SCOPE, $login, $ip);
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
