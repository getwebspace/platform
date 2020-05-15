<?php declare(strict_types=1);

namespace App\Application\Actions\Api\User\Subscriber;

use App\Application\Actions\Cup\User\UserAction;

class SubscribeAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'email' => $this->request->getParam('email'),
                'date'  => $this->request->getParam('date'),
            ];

            $check = \App\Domain\Filters\User::subscribeCreate($data);

            if ($check === true) {
                $model = new \App\Domain\Entities\User\Subscriber($data);

                $this->entityManager->persist($model);
                $this->entityManager->flush();

                return $this->response->withStatus(201);
            }
        }

        return $this->response->withStatus(204);
    }
}
