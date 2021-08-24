<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Application\Actions\Cup\User\UserAction;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;

class LoginPageAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        $identifier = $this->parameter('user_login_type', 'username');

        if ($this->request->isPost()) {
            $data = [
                'phone' => $this->request->getParam('phone', ''),
                'email' => $this->request->getParam('email', ''),
                'username' => $this->request->getParam('username', ''),
                'password' => $this->request->getParam('password', ''),

                'agent' => $this->request->getServerParam('HTTP_USER_AGENT'),
                'ip' => $this->getRequestRemoteIP(),

                'redirect' => $this->request->getParam('redirect'),
            ];

            if ($this->isRecaptchaChecked()) {
                try {
                    $user = $this->userService->read([
                        'identifier' => $data[$identifier],
                        'password' => $data['password'],
                        'agent' => $data['agent'],
                        'ip' => $data['ip'],
                        'status' => \App\Domain\Types\UserStatusType::STATUS_WORK,
                    ]);
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

                    return $this->response->withRedirect($data['redirect'] ? $data['redirect'] : '/cup');
                } catch (UserNotFoundException $exception) {
                    $this->addError($identifier, $exception->getMessage());
                } catch (WrongPasswordException $exception) {
                    $this->addError('password', $exception->getMessage());
                }
            } else {
                $this->addError('grecaptcha', \App\Domain\References\Errors\Common::WRONG_GRECAPTCHA);
            }
        }

        return $this->respondWithTemplate('cup/auth/login.twig', ['identifier' => $identifier]);
    }
}
