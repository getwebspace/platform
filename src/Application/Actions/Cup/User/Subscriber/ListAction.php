<?php

namespace App\Application\Actions\Cup\User\Subscriber;

use App\Application\Actions\Cup\User\UserAction;

class ListAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->subscriberRepository->findAll());

        return $this->respondRender('cup/user/subscriber/index.twig', ['list' => $list]);
    }
}
