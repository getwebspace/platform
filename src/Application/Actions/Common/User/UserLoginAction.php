<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Entities\User;
use App\Domain\OAuth\FacebookOAuthProvider;
use App\Domain\OAuth\VKOAuthProvider;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\EmailBannedException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongPasswordException;

class UserLoginAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        $identifier = $this->parameter('user_login_type', 'username');
        $user = $this->process($identifier);

        if ($user) {
            $session = $user->getSession();

            // create new session
            if ($session === null) {
                $session = $this->userSessionService->create([
                    'user' => $user,
                    'date' => 'now',
                    'agent' => $this->request->getServerParam('HTTP_USER_AGENT'),
                    'ip' => $this->getRequestRemoteIP(),
                ]);
            } else {
                // update session
                $session = $this->userSessionService->update($session, [
                    'date' => 'now',
                    'agent' => $this->request->getServerParam('HTTP_USER_AGENT'),
                    'ip' => $this->getRequestRemoteIP(),
                ]);
            }

            setcookie('uuid', $user->getUuid()->toString(), time() + \App\Domain\References\Date::YEAR, '/');
            setcookie('session', $session->getHash(), time() + \App\Domain\References\Date::YEAR, '/');

            return $this->response->withRedirect($this->request->getParam('redirect', '/user/profile'));
        }

        return $this->respond($this->parameter('user_login_template', 'user.login.twig'), ['identifier' => $identifier]);
    }

    protected function process(string $identifier): ?User
    {
        switch ($this->request->getParam('provider', 'self')) {
            // via login/email
            case 'self':
                if ($this->request->isPost()) {
                    $data = [
                        'phone' => $this->request->getParam('phone'),
                        'email' => $this->request->getParam('email'),
                        'username' => $this->request->getParam('username'),
                        'password' => $this->request->getParam('password', ''),
                    ];

                    if ($this->isRecaptchaChecked()) {
                        try {
                            return $this->userService->read([
                                'identifier' => $data[$identifier],
                                'password' => $data['password'],
                            ]);
                        } catch (UserNotFoundException $e) {
                            $this->addError($identifier, $e->getMessage());
                        } catch (WrongPasswordException $e) {
                            $this->addError('password', $e->getMessage());
                        }
                    }

                    $this->addError('grecaptcha', \App\Domain\References\Errors\Common::WRONG_GRECAPTCHA);
                }

                break;

            // via facebook
            case 'facebook':
                $provider = new FacebookOAuthProvider($this->container);
                $token = $provider->getToken($this->request->getParam('code'));

                if ($token) {
                    try {
                        if (($integration = $provider->callback($token, $this->request->getAttribute('user'))) !== null) {
                            return $integration->getUser();
                        }
                        $this->addError($identifier, 'EXCEPTION_USER_NOT_FOUND');
                    } catch (EmailAlreadyExistsException | EmailBannedException | WrongEmailValueException $e) {
                        $this->addError($identifier, $e->getMessage());
                    }
                }

                break;

            // via vk
            case 'vk':
                $provider = new VKOAuthProvider($this->container);
                $token = $provider->getToken($this->request->getParam('code'));

                if ($token) {
                    try {
                        if (($integration = $provider->callback($token, $this->request->getAttribute('user'))) !== null) {
                            return $integration->getUser();
                        }
                        $this->addError($identifier, 'EXCEPTION_USER_NOT_FOUND');
                    } catch (EmailAlreadyExistsException | EmailBannedException | WrongEmailValueException $e) {
                        $this->addError($identifier, $e->getMessage());
                    }
                }

                break;
        }

        return null;
    }
}
