<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Application\Actions\Cup\User\UserAction;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;

class LoginPageAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $identifier = $this->parameter('user_login_type', 'username');

        if ($this->isPost()) {
            $data = [
                'identifier' => $this->getParam('identifier', ''),
                'password' => $this->getParam('password', ''),

                'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                'ip' => $this->getRequestRemoteIP(),

                'redirect' => $this->getParam('redirect'),
            ];

            if ($this->isRecaptchaChecked()) {
                try {
                    $user = $this->userService->read([
                        'identifier' => $data['identifier'],
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

                    return $this->respondWithRedirect($data['redirect'] ? $data['redirect'] : '/cup');
                } catch (UserNotFoundException $exception) {
                    $this->addError($identifier, $exception->getMessage());
                } catch (WrongPasswordException $exception) {
                    $this->addError('password', $exception->getMessage());
                }
            } else {
                $this->addError('grecaptcha', 'EXCEPTION_WRONG_GRECAPTCHA');
            }
        }

        return $this->respondWithTemplate('cup/auth/login.twig', ['identifier' => $identifier]);
    }
}
