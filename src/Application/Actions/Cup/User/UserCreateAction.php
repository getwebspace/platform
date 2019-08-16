<?php

namespace Application\Actions\Cup\User;

use Exception;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Slim\Http\Response;

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
                'level' => $this->request->getParam('level'),
            ];

            $check = \Domain\Filters\User::check($data);

            if ($check === true) {
                try {
                    $uuid = Uuid::uuid4();
                    $session = new \Domain\Entities\User\Session();
                    $session->set('uuid', $uuid);
                    $this->entityManager->persist($session);

                    $model = new \Domain\Entities\User($data);
                    $model->set('uuid', $uuid);
                    $model->register = $model->change = new \DateTime();
                    $model->session = $session;
                    $this->entityManager->persist($model);

                    $this->entityManager->flush();

                    return $this->response->withAddedHeader('Location', '/cup/user');
                } catch (Exception $e) {
                    // todo nothing
                }
            }
        }

        return $this->respondRender('cup/user/form.twig');
    }
}
