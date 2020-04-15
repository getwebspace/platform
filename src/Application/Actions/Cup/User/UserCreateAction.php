<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use Ramsey\Uuid\Uuid;

class UserCreateAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'username' => $this->request->getParam('username'),
                'password' => $this->request->getParam('password'),
                'firstname' => $this->request->getParam('firstname'),
                'lastname' => $this->request->getParam('lastname'),
                'email' => $this->request->getParam('email'),
                'allow_mail' => $this->request->getParam('allow_mail'),
                'phone' => $this->request->getParam('phone'),
                'level' => $this->request->getParam('level'),
            ];

            $check = \App\Domain\Filters\User::check($data);

            if ($check === true) {
                $uuid = Uuid::uuid4();
                $session = new \App\Domain\Entities\User\Session();
                $session->set('uuid', $uuid);
                $this->entityManager->persist($session);

                $model = new \App\Domain\Entities\User($data);
                $model->set('uuid', $uuid);
                $model->register = $model->change = new \DateTime();
                $model->session = $session;
                $this->entityManager->persist($model);

                $this->entityManager->flush();

                switch (true) {
                    case $this->request->getParam('save', 'exit') === 'exit':
                        return $this->response->withAddedHeader('Location', '/cup/user')->withStatus(301);
                    default:
                        return $this->response->withAddedHeader('Location', '/cup/user/' . $model->uuid . '/edit')->withStatus(301);
                }
            } else {
                $this->addErrorFromCheck($check);
            }
        }

        return $this->respondWithTemplate('cup/user/form.twig');
    }
}
