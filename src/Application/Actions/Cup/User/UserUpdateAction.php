<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use App\Domain\Exceptions\WrongEmailValueException;
use App\Domain\Exceptions\WrongPhoneValueException;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\UserService;

class UserUpdateAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid')) {
            $user = $this->userService->read(['uuid' => $this->resolveArg('uuid')]);

            if ($user) {
                if ($this->request->isPost()) {
                    try {
                        $this->userService->update($user, [
                            'username' => $this->request->getParam('username'),
                            'firstname' => $this->request->getParam('firstname'),
                            'lastname' => $this->request->getParam('lastname'),
                            'email' => $this->request->getParam('email'),
                            'allow_mail' => $this->request->getParam('allow_mail'),
                            'phone' => $this->request->getParam('phone'),
                            'password' => $this->request->getParam('password'),
                            'level' => $this->request->getParam('level'),
                            'status' => $this->request->getParam('status'),
                        ]);
                        $user = $this->handlerEntityFiles($user);

                        switch (true) {
                            case $this->request->getParam('save', 'exit') === 'exit':
                                return $this->response->withRedirect('/cup/user');
                            default:
                                return $this->response->withRedirect('/cup/user/' . $user->getUuid() . '/edit');
                        }
                    } catch (UsernameAlreadyExistsException $e) {
                        $this->addError('username', $e->getMessage());
                    } catch (WrongEmailValueException|EmailAlreadyExistsException $e) {
                        $this->addError('email', $e->getMessage());
                    } catch (WrongPhoneValueException|PhoneAlreadyExistsException $e) {
                        $this->addError('phone', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate('cup/user/form.twig', ['user' => $user]);
            }
        }

        return $this->response->withRedirect('/cup/user');
    }
}
