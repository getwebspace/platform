<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;

class UserCreateAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            try {
                $user = $this->userService->create([
                    'username' => $this->request->getParam('username'),
                    'password' => $this->request->getParam('password'),
                    'firstname' => $this->request->getParam('firstname'),
                    'lastname' => $this->request->getParam('lastname'),
                    'email' => $this->request->getParam('email'),
                    'allow_mail' => $this->request->getParam('allow_mail'),
                    'phone' => $this->request->getParam('phone'),
                    'level' => $this->request->getParam('level'),
                ]);
                $user = $this->processEntityFiles($user);

                switch (true) {
                    case $this->request->getParam('save', 'exit') === 'exit':
                        return $this->response->withRedirect('/cup/user');
                    default:
                        return $this->response->withRedirect('/cup/user/' . $user->getUuid() . '/edit');
                }
            } catch (UsernameAlreadyExistsException $e) {
                $this->addError('username', $e->getMessage());
            } catch (EmailAlreadyExistsException $e) {
                $this->addError('email', $e->getMessage());
            } catch (PhoneAlreadyExistsException $e) {
                $this->addError('phone', $e->getMessage());
            }
        }

        return $this->respondWithTemplate('cup/user/form.twig');
    }
}
