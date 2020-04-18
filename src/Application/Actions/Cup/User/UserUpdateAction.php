<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

class UserUpdateAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid')) {
            $user = $this->users->findByUuid($this->resolveArg('uuid'));

            if ($user) {
                if ($this->request->isPost()) {
                    // смена username
                    if (
                        ($username = $this->request->getParam('username')) !== null &&
                        $this->users->findOneByUsername($username) === null
                    ) {
                        $user->setUsername($username);
                    }

                    // смена email
                    if (
                        ($email = $this->request->getParam('email')) !== null &&
                        $this->users->findOneByUsername($email) === null
                    ) {
                        $user->setEmail($email);
                    }

                    $user
                        ->setPassword($this->request->getParam('password'))
                        ->setFirstname($this->request->getParam('firstname'))
                        ->setLastname($this->request->getParam('lastname'))
                        ->setAllowMail($this->request->getParam('allow_mail'))
                        ->setPhone($this->request->getParam('phone'))
                        ->setLevel($this->request->getParam('level'))
                        ->setStatus($this->request->getParam('status'))
                        ->setChange('now');

                    $this->entityManager->flush();

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

        return $this->response->withAddedHeader('Location', '/cup/user')->withStatus(301);
    }
}
