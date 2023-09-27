<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\EmailBannedException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongPhoneValueException;
use App\Domain\Service\User\Exception\WrongUsernameValueException;

class UserRegisterAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            $data = [
                'firstname' => $this->getParam('firstname', ''),
                'lastname' => $this->getParam('lastname', ''),
                'username' => $this->getParam('username', ''),
                'email' => $this->getParam('email', ''),
                'phone' => $this->getParam('phone', ''),
                'address' => $this->getParam('address', ''),
                'additional' => $this->getParam('additional', ''),
                'allow_mail' => $this->getParam('allow_mail', true),
                'password' => $this->getParam('password'),
                'password_again' => $this->getParam('password_again'),
                'external_id' => $this->getParam('external_id', ''),
            ];

            if ($this->isRecaptchaChecked()) {
                if ($data['password'] === $data['password_again']) {
                    try {
                        $groupUuid = $this->parameter('user_group');
                        $user = $this->userService->create([
                            'firstname' => $data['firstname'],
                            'lastname' => $data['lastname'],
                            'username' => $data['username'],
                            'email' => $data['email'],
                            'phone' => $data['phone'],
                            'address' => $data['address'],
                            'additional' => $data['additional'],
                            'allow_mail' => $data['allow_mail'],
                            'password' => $data['password'],
                            'group' => $groupUuid ? $this->userGroupService->read(['uuid' => $groupUuid]) : null,
                            'external_id' => $data['external_id'],
                        ]);

                        $this->container->get(\App\Application\PubSub::class)->publish('common:user:register', $user);

                        return $this->respondWithRedirect('/user/login');
                    } catch (MissingUniqueValueException $e) {
                        $this->addError('email', $e->getMessage());
                        $this->addError('username', $e->getMessage());
                        $this->addError('phone', $e->getMessage());
                    } catch (WrongUsernameValueException|UsernameAlreadyExistsException $e) {
                        $this->addError('username', $e->getMessage());
                    } catch (WrongEmailValueException|EmailAlreadyExistsException|EmailBannedException $e) {
                        $this->addError('email', $e->getMessage());
                    } catch (WrongPhoneValueException|PhoneAlreadyExistsException $exception) {
                        $this->addError('phone', $exception->getMessage());
                    }
                }
            }

            $this->addError('grecaptcha', 'EXCEPTION_WRONG_GRECAPTCHA');
        }

        return $this->respond($this->parameter('user_register_template', 'user.register.twig'));
    }
}
