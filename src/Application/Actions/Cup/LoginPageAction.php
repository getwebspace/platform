<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Application\Actions\Cup\User\UserAction;
use App\Application\Auth;
use App\Domain\Service\User\Exception\UserNotConfirmedException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Traits\UseThrottle;
use Psr\Container\ContainerInterface;

class LoginPageAction extends UserAction
{
    use UseThrottle;

    private const THROTTLE_SCOPE = 'cup-login';

    private Auth $auth;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->auth = $container->get(Auth::class);
    }

    protected function action(): \Slim\Psr7\Response
    {
        // first install redirect
        if (!file_exists(LOCK_FILE)) {
            return $this->respondWithRedirect('/cup/system');
        }

        $identifier = $this->parameter('user_login_type', 'username');
        $login = (string) $this->getParam('identifier', '');
        $ip = $this->getRequestRemoteIP();

        if ($this->isPost()) {
            if ($this->isThrottled(self::THROTTLE_SCOPE, $login, $ip)) {
                $this->addError('identifier', 'EXCEPTION_TOO_MANY_ATTEMPTS');
            } elseif (!$this->isRecaptchaChecked()) {
                // used to be reported on plain GET as well, so the login page
                // greeted every visitor with a captcha error
                $this->addError('grecaptcha', 'EXCEPTION_WRONG_GRECAPTCHA');
            } else {
                try {
                    $result = $this->auth->login(
                        'BasicAuthProvider',
                        [
                            'identifier' => $login,
                            'password' => $this->getParam('password'),
                        ],
                        [
                            'redirect' => $this->request->getUri()->getPath(),
                            'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                            'ip' => $ip,
                            'comment' => 'Login via common page',
                        ]
                    );

                    $this->throttleClear(self::THROTTLE_SCOPE, $login, $ip);
                    $this->setAuthCookies($result);

                    return $this->respondWithRedirect($this->getRedirectParam('redirect', '/cup'));
                } catch (UserNotConfirmedException $e) {
                    $this->addError('identifier', $e->getMessage());
                } catch (UserNotFoundException $e) {
                    $this->throttleHit(self::THROTTLE_SCOPE, $login, $ip);
                    $this->addError('identifier', $e->getMessage());
                } catch (WrongPasswordException $e) {
                    $this->throttleHit(self::THROTTLE_SCOPE, $login, $ip);
                    $this->addError('password', $e->getMessage());
                }
            }
        }

        return $this->respondWithTemplate('cup/auth/login.twig', [
            'identifier' => $identifier,
            'oauth' => $this->container->get('plugin')->get()->filter(function ($plugin) {
                return is_a($plugin, \App\Domain\Plugin\AbstractOAuthPlugin::class);
            }),
        ]);
    }
}
