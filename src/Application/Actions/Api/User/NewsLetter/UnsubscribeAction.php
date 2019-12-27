<?php

namespace App\Application\Actions\Api\User\Subscriber;

use App\Application\Actions\Cup\User\UserAction;

class UnsubscribeAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \App\Domain\Entities\User\Subscriber $item */
            $item = $this->subscriberRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty()) {
                $this->entityManager->remove($item);
                $this->entityManager->flush();

                return $this->response->withStatus(202);
            }
        }

        return $this->response->withStatus(208);
    }
}
