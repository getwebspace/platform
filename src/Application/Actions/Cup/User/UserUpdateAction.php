<?php

namespace App\Application\Actions\Cup\User;

use Exception;

class UserUpdateAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \App\Domain\Entities\User $item */
            $item = $this->userRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty()) {
                if ($this->request->isPost()) {
                    $data = [
                        'uuid' => $item->uuid,
                        'username' => $this->request->getParam('username'),
                        'password' => $this->request->getParam('password'),
                        'firstname' => $this->request->getParam('firstname'),
                        'lastname' => $this->request->getParam('lastname'),
                        'email' => $this->request->getParam('email'),
                        'level' => $this->request->getParam('level'),
                        'status' => $this->request->getParam('status'),
                    ];

                    $check = \App\Domain\Filters\User::check($data);

                    if ($check === true) {
                        try {
                            $item->replace($data);
                            $item->change = new \DateTime();
                            $this->entityManager->persist($item);
                            $this->entityManager->flush();

                            return $this->response->withAddedHeader('Location', '/cup/user');
                        } catch (Exception $e) {
                            // todo nothing
                        }
                    }
                }

                return $this->respondRender('cup/user/form.twig', ['item' => $item]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/user');
    }
}
