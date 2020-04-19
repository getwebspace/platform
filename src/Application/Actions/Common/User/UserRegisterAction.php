<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\UserService;

class UserRegisterAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        $identifier = $this->getParameter('user_login_type', 'username');

        if ($this->request->isPost()) {
            $data = [
                'email' => $this->request->getParam('email'),
                'username' => $this->request->getParam('username'),
                'password' => $this->request->getParam('password'),
                'password_again' => $this->request->getParam('password_again'),
            ];

            if ($this->isRecaptchaChecked()) {
                if ($data['password'] === $data['password_again']) {
                    try {
                        $userService = UserService::getFromContainer($this->container);
                        $userService->createByRegister([
                            $identifier => $data[$identifier],
                            'password' => $data['password'],
                        ]);

                        return $this->response->withRedirect('/user/login');
                    } catch (MissingUniqueValueException $exception) {
                        $this->addError($identifier, $exception->getMessage());
                    } catch (UsernameAlreadyExistsException $exception) {
                        $this->addError('username', $exception->getMessage());
                    } catch (EmailAlreadyExistsException $exception) {
                        $this->addError('email', $exception->getMessage());
                    }
                }
            }

            $this->addError('grecaptcha', \App\Domain\References\Errors\Common::WRONG_GRECAPTCHA);
        }

        return $this->respondWithTemplate($this->getParameter('user_register_template', 'user.register.twig'));
    }
}
