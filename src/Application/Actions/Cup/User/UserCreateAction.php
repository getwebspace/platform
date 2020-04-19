<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\UserService;

class UserCreateAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            try {
                $userService = UserService::getFromContainer($this->container);
                $user = $userService->createByCup([
                    'username' => $this->request->getParam('username'),
                    'password' => $this->request->getParam('password'),
                    'firstname' => $this->request->getParam('firstname'),
                    'lastname' => $this->request->getParam('lastname'),
                    'email' => $this->request->getParam('email'),
                    'allow_mail' => $this->request->getParam('allow_mail'),
                    'phone' => $this->request->getParam('phone'),
                    'level' => $this->request->getParam('level'),
                ]);

                switch (true) {
                    case $this->request->getParam('save', 'exit') === 'exit':
                        return $this->response->withRedirect('/cup/user');
                    default:
                        return $this->response->withRedirect('/cup/user/' . $user->getUuid() . '/edit');
                }
            } catch (UsernameAlreadyExistsException $exception) {
                $this->addError('username', $exception->getMessage());
            } catch (EmailAlreadyExistsException $exception) {
                $this->addError('email', $exception->getMessage());
            }
        }

        return $this->respondWithTemplate('cup/user/form.twig');
    }
}
