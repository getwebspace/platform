<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

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
                        'allow_mail' => $this->request->getParam('allow_mail'),
                        'phone' => $this->request->getParam('phone'),
                        'level' => $this->request->getParam('level'),
                        'status' => $this->request->getParam('status'),
                    ];

                    $check = \App\Domain\Filters\User::check($data);

                    if ($check === true) {
                        $item->replace($data);
                        $item->change = new \DateTime();
                        $this->entityManager->persist($item);
                        $this->entityManager->flush();

                        if ($this->request->getParam('save', 'exit') === 'exit') {
                            return $this->response->withAddedHeader('Location', '/cup/user')->withStatus(301);
                        }

                        return $this->response->withAddedHeader('Location', $this->request->getUri()->getPath())->withStatus(301);
                    }
                    $this->addErrorFromCheck($check);
                }

                return $this->respondWithTemplate('cup/user/form.twig', ['item' => $item]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/user')->withStatus(301);
    }
}
