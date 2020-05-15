<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Service\User\UserService;

class LoginPageAction extends AbstractAction
{
    protected function action(): \Slim\Http\Response
    {
        $identifier = $this->getParameter('user_login_type', 'username');

        if ($this->request->isPost()) {
            $data = [
                'phone' => $this->request->getParam('phone'),
                'email' => $this->request->getParam('email'),
                'username' => $this->request->getParam('username'),
                'password' => $this->request->getParam('password'),

                'agent' => $this->request->getServerParam('HTTP_USER_AGENT'),
                'ip' => $this->request->getServerParam('REMOTE_ADDR'),

                'redirect' => $this->request->getParam('redirect'),
            ];

            if ($this->isRecaptchaChecked()) {
                try {
                    $userService = UserService::getFromContainer($this->container);
                    $user = $userService->read([
                        'identifier' => $data[$identifier],
                        'password' => $data['password'],
                        'agent' => $data['agent'],
                        'ip' => $data['ip'],
                    ]);

                    setcookie('uuid', $user->getUuid()->toString(), time() + \App\Domain\References\Date::YEAR, '/');
                    setcookie('session', $user->getSession()->getHash(), time() + \App\Domain\References\Date::YEAR, '/');

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
