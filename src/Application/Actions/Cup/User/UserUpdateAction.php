<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use App\Domain\Service\User\UserService;

class UserUpdateAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid')) {
            $user = $this->users->findByUuid($this->resolveArg('uuid'));

            if ($user) {
                if ($this->request->isPost()) {
                    $userService = UserService::getFromContainer($this->container);
                    $userService->change(
                        $user,
                        [
                            'username' => $this->request->getParam('username'),
                            'firstname' => $this->request->getParam('firstname'),
                            'lastname' => $this->request->getParam('lastname'),
                            'email' => $this->request->getParam('email'),
                            'allow_mail' => $this->request->getParam('allow_mail'),
                            'phone' => $this->request->getParam('phone'),
                            'password' => $this->request->getParam('password'),
                            'level' => $this->request->getParam('level'),
                            'status' => $this->request->getParam('status'),
                        ]
                    );

                    switch (true) {
                        case $this->request->getParam('save', 'exit') === 'exit':
                            return $this->response->withRedirect('/cup/user');
                        default:
                            return $this->response->withRedirect('/cup/user/' . $user->getUuid() . '/edit');
                    }
                }

                return $this->respondWithTemplate('cup/user/form.twig', ['user' => $user]);
            }
        }

        return $this->response->withRedirect('/cup/user');
    }
}
