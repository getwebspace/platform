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
            'identifier' => $this->getParam('identifier'),
            'username' => $this->getParam('username'),
            'phone' => $this->getParam('phone'),
            'email' => $this->getParam('email'),
            'password' => $this->getParam('password'),
            'code' => $this->getParam('code'),
            'state' => $this->getParam('state'),
        ];

        // a form may send one `identifier` field (e-mail, phone or username,
        // the same as the cup login), otherwise the field from user_login_type
        $field = !blank($data['identifier']) ? 'identifier' : $identifier;

        if (
            ($this->isGet() && $data['code'] && $data['state'])
            || ($this->isPost() && (($data[$field] && $data['password']) || $provider))
        ) {
            $login = (string) ($data[$field] ?? '');
            $ip = $this->getRequestRemoteIP();

            if ($this->isPost() && $this->isThrottled(self::THROTTLE_SCOPE, $login, $ip)) {
                $this->addError($field, 'EXCEPTION_TOO_MANY_ATTEMPTS');
            } elseif ($this->isPost() && $this->isRecaptchaChecked() || $this->isGet()) {
                try {
                    $result = $this->auth->login(
                        $provider,
                        [
                            $field => $data[$field],
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
                    $this->addError($field, $e->getMessage());
                } catch (UserNotFoundException $e) {
                    $this->throttleHit(self::THROTTLE_SCOPE, $login, $ip);
                    $this->addError($field, $e->getMessage());
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
