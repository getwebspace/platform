<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Exceptions\WrongEmailValueException;
use App\Domain\Exceptions\WrongPhoneValueException;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\UserService;

class UserRegisterAction extends UserAction
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
                'password_again' => $this->request->getParam('password_again'),
            ];

            if ($this->isRecaptchaChecked()) {
                if ($data['password'] === $data['password_again']) {
                    try {
                        $userService = UserService::getWithContainer($this->container);
                        $userService->create([
                            $identifier => $data[$identifier],
                            'password' => $data['password'],
                        ]);

                        return $this->response->withRedirect('/user/login');
                    } catch (MissingUniqueValueException $e) {
                        $this->addError($identifier, $e->getMessage());
                    } catch (UsernameAlreadyExistsException $e) {
                        $this->addError('username', $e->getMessage());
                    } catch (WrongEmailValueException|EmailAlreadyExistsException $e) {
                        $this->addError('email', $e->getMessage());
                    } catch (WrongPhoneValueException|PhoneAlreadyExistsException $exception) {
                        $this->addError('phone', $exception->getMessage());
                    }
                }
            }

            $this->addError('grecaptcha', \App\Domain\References\Errors\Common::WRONG_GRECAPTCHA);
        }

        return $this->respondWithTemplate($this->getParameter('user_register_template', 'user.register.twig'));
    }
}
