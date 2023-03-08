<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Entities\User;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Traits\SecurityTrait;

class UserLoginAction extends UserAction
{
    use SecurityTrait;

    protected function action(): \Slim\Psr7\Response
    {
        $identifier = $this->parameter('user_login_type', 'username');
        $provider = $this->getParam('provider', 'self');
        $user = $this->process($identifier, $provider);

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

        return $this->respond($this->parameter('user_login_template', 'user.login.twig'), [
            'identifier' => $identifier,
            'provider' => $provider,
            'oauth' => $this->getOAuthProviders(true),
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

                    $this->addError('grecaptcha', 'EXCEPTION_WRONG_GRECAPTCHA');
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
                                    if (!$user->getAuthCode() || (new \DateTime('now'))->diff($user->getChange())->i >= 10) {
                                        // new code
                                        $code = implode('-', [random_int(100, 999), random_int(100, 999), random_int(100, 999)]);

                                        // update auth code
                                        $this->userService->update($user, ['auth_code' => $code]);

                                        // add task send auth code to mail
                                        $task = new \App\Domain\Tasks\SendMailTask($this->container);
                                        $task->execute([
                                            'to' => $user->getEmail(),
                                            'template' => $this->parameter('user_auth_code_mail_template', 'user.mail.code.twig'),
                                            'data' => ['code' => $code],
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
        }

        return null;
    }
}
