<?php

namespace App\Application\Actions\Cup\User\Subscriber;

use App\Application\Actions\Cup\User\UserAction;

class CreateAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'email' => $this->request->getParam('email'),
            ];

            $check = \App\Domain\Filters\User::subscribeCreate($data);

            if ($check === true) {
                $model = new \App\Domain\Entities\User\Subscriber($data);

                $this->entityManager->persist($model);
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/user/subscriber')->withStatus(301);
    }
}
