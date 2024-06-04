<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Application\Actions\Cup\User\UserAction;
use App\Application\Auth;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use Psr\Container\ContainerInterface;

class LoginPageAction extends UserAction
{
    private Auth $auth;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->auth = $container->get(Auth::class);
    }

    protected function action(): \Slim\Psr7\Response
    {
        $identifier = $this->parameter('user_login_type', 'username');

        try {
            if ($this->isPost() && $this->isRecaptchaChecked()) {
                $result = $this->auth->login(
                    'BasicAuthProvider',
                    [
                        'identifier' => $this->getParam('identifier', ''),
                        'password' => $this->getParam('password'),
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

                return $this->respondWithRedirect($this->getParam('redirect', '/cup'));
            }
            $this->addError('grecaptcha', 'EXCEPTION_WRONG_GRECAPTCHA');
        } catch (UserNotFoundException $e) {
            $this->addError($identifier, $e->getMessage());
        } catch (WrongPasswordException $e) {
            $this->addError('password', $e->getMessage());
        }

        return $this->respondWithTemplate('cup/auth/login.twig', [
            'identifier' => $identifier,
            'oauth' => $this->container->get('plugin')->get()->filter(function ($plugin) {
                return is_a($plugin, \App\Domain\Plugin\AbstractOAuthPlugin::class);
            }),
        ]);
    }
}
