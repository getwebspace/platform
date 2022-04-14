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
use DateTime;

class UserLoginAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $identifier = $this->parameter('user_login_type', 'username');
        $provider = $this->getParam('provider', 'self');
        $user = $this->process($identifier, $provider);

        if ($user) {
            $session = $user->getSession();

            // create new session
            if ($session === null) {
                $session = $this->userSessionService->create([
                    'user' => $user,
                    'date' => 'now',
                    'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                    'ip' => $this->getRequestRemoteIP(),
                ]);
            } else {
                // update session
                $session = $this->userSessionService->update($session, [
                    'date' => 'now',
                    'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                    'ip' => $this->getRequestRemoteIP(),
                ]);
            }

            setcookie('uuid', $user->getUuid()->toString(), time() + \App\Domain\References\Date::YEAR, '/');
            setcookie('session', $session->getHash(), time() + \App\Domain\References\Date::YEAR, '/');

            $this->container->get(\App\Application\PubSub::class)->publish('common:user:login', $user);

            return $this->respondWithRedirect($this->getParam('redirect', '/user/profile'));
        }

        return $this->respond($this->parameter('user_login_template', 'user.login.twig'), [
            'identifier' => $identifier,
            'provider' => $provider,
        ]);
    }

    protected function process(string $identifier, string $provider): ?User
    {
        switch ($provider) {
            // via login/email/phone with password
            case 'self':
                if ($this->isPost()) {
                    $data = [
                        'phone' => $this->getParam('phone', ''),
                        'email' => $this->getParam('email', ''),
                        'username' => $this->getParam('username', ''),
                        'password' => $this->getParam('password', ''),
                    ];

                    if ($this->isRecaptchaChecked()) {
                        try {
                            return $this->userService->read([
                                $identifier => $data[$identifier],
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

            // via login/email/phone with code
            case 'code':
                if ($this->isPost() && $this->parameter('user_auth_code_is_enabled', 'no') === 'yes') {
                    $data = [
                        'phone' => $this->getParam('phone', ''),
                        'email' => $this->getParam('email', ''),
                        'username' => $this->getParam('username', ''),
                        'code' => $this->getParam('code', ''),
                    ];

                    if ($this->isRecaptchaChecked()) {
                        try {
                            $user = $this->userService->read([
                                $identifier => $data[$identifier],
                            ]);

                            if (isset($this->getParams()['sendcode'])) {
                                if ($user->getEmail()) {
                                    if (!$user->getAuthCode() || (new DateTime('now'))->diff($user->getChange())->i >= 10) {
                                        // new code
                                        $code = implode('-', [random_int(100, 999), random_int(100, 999), random_int(100, 999)]);

                                        // update auth code
                                        $this->userService->update($user, ['auth_code' => $code]);

                                        // add task send auth code to mail
                                        $task = new \App\Domain\Tasks\SendMailTask($this->container);
                                        $task->execute([
                                            'to' => $user->getEmail(),
                                            'body' => $this->render(
                                                $this->parameter('user_auth_code_mail_template', 'user.mail.code.twig'),
                                                ['code' => $code]
                                            ),
                                            'isHtml' => true,
                                        ]);
                                        \App\Domain\AbstractTask::worker($task);
                                    } else {
                                        $this->addError('code', 'EXCEPTION_WRONG_CODE_TIMEOUT');
                                    }
                                } else {
                                    $this->addError('code', 'EXCEPTION_EMAIL_MISSING');
                                }
                            } else {
                                // check code
                                if ($data['code'] && $data['code'] === $user->getAuthCode()) {
                                    $this->userService->update($user, ['auth_code' => '']);

                                    return $user;
                                }

                                $this->addError('code', 'EXCEPTION_WRONG_CODE');
                            }
                        } catch (UserNotFoundException $e) {
                            $this->addError($identifier, $e->getMessage());
                        }
                    }
                }

                break;

            // via facebook
            case 'facebook':
                $provider = new FacebookOAuthProvider($this->container);
                $token = $provider->getToken($this->getParam('code'));

                if ($token) {
                    try {
                        if (($integration = $provider->callback($token, $this->request->getAttribute('user'))) !== null) {
                            return $integration->getUser();
                        }
                        $this->addError($identifier, 'EXCEPTION_USER_NOT_FOUND');
                    } catch (EmailAlreadyExistsException|EmailBannedException|WrongEmailValueException $e) {
                        $this->addError($identifier, $e->getMessage());
                    }
                }

                break;

            // via vk
            case 'vk':
                $provider = new VKOAuthProvider($this->container);
                $token = $provider->getToken($this->getParam('code'));

                if ($token) {
                    try {
                        if (($integration = $provider->callback($token, $this->request->getAttribute('user'))) !== null) {
                            return $integration->getUser();
                        }
                        $this->addError($identifier, 'EXCEPTION_USER_NOT_FOUND');
                    } catch (EmailAlreadyExistsException|EmailBannedException|WrongEmailValueException $e) {
                        $this->addError($identifier, $e->getMessage());
                    }
                }

                break;
        }

        return null;
    }
}
