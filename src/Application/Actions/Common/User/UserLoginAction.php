<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Service\User\UserService;

class UserLoginAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        $identifier = $this->getParameter('user_login_type', 'username');

        if ($this->request->isPost()) {
            $data = [
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
                    $user = $userService->getByLogin([
                        'identifier' => $data[$identifier],
                        'password' => $data['password'],
                        'agent' => $data['agent'],
                        'ip' => $data['ip'],
                    ]);

                    $hash = sha1(
                        'salt:' . ($this->container->get('secret')['salt'] ?? '') . ';' .
                        'uuid:' . $user->getUuid()->toString() . ';' .
                        'ip:' . md5($data['ip']) . ';' .
                        'agent:' . md5($data['agent']) . ';' .
                        'date:' . $user->getSession()->getDate()->getTimestamp()
                    );

                    setcookie('uuid', $user->getUuid()->toString(), time() + \App\Domain\References\Date::YEAR, '/');
                    setcookie('session', $hash, time() + \App\Domain\References\Date::YEAR, '/');

                    return $this->response->withRedirect($data['redirect'] ? $data['redirect'] : '/user/profile');
                } catch (UserNotFoundException $exception) {
                    $this->addError($identifier, $exception->getMessage());
                } catch (WrongPasswordException $exception) {
                    $this->addError('password', $exception->getMessage());
                }
            }

            $this->addError('grecaptcha', \App\Domain\References\Errors\Common::WRONG_GRECAPTCHA);
        }

        return $this->respondWithTemplate($this->getParameter('user_login_template', 'user.login.twig'), ['identifier' => $identifier]);
    }
}
