<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Exceptions\WrongEmailValueException;
use App\Domain\Exceptions\WrongPhoneValueException;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;

class UserRegisterAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'firstname' => $this->request->getParam('firstname', ''),
                'lastname' => $this->request->getParam('lastname', ''),
                'username' => $this->request->getParam('username', ''),
                'email' => $this->request->getParam('email', ''),
                'phone' => $this->request->getParam('phone', ''),
                'address' => $this->request->getParam('address', ''),
                'additional' => $this->request->getParam('additional', ''),
                'password' => $this->request->getParam('password'),
                'password_again' => $this->request->getParam('password_again'),
            ];

            if ($this->isRecaptchaChecked()) {
                if ($data['password'] === $data['password_again']) {
                    try {
                        $groupUuid = $this->parameter('user_group', null);
                        $this->userService->create([
                            'firstname' => $data['firstname'],
                            'lastname' => $data['lastname'],
                            'username' => $data['username'],
                            'email' => $data['email'],
                            'phone' => $data['phone'],
                            'address' => $data['address'],
                            'additional' => $data['additional'],
                            'password' => $data['password'],
                            'group' => $groupUuid ? $this->userGroupService->read(['uuid' => $groupUuid]) : null,
                        ]);

                        return $this->response->withRedirect('/user/login');
                    } catch (MissingUniqueValueException $e) {
                        $this->addError('email', $e->getMessage());
                        $this->addError('username', $e->getMessage());
                        $this->addError('phone', $e->getMessage());
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

        return $this->respondWithTemplate($this->parameter('user_register_template', 'user.register.twig'));
    }
}
