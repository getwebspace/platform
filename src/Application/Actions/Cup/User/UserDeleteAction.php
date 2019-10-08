<?php

namespace App\Application\Actions\Cup\User;

class UserDeleteAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \App\Domain\Entities\User $item */
            $item = $this->userRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty() && $this->request->isPost()) {
                $item->set('status', \App\Domain\Types\UserStatusType::STATUS_DELETE);
                $this->entityManager->persist($item);
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/user')->withStatus(301);
    }
}
