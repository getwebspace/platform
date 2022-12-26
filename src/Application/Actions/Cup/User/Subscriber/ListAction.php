<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\Subscriber;

use App\Application\Actions\Cup\User\UserAction;

class ListAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/user/subscriber/index.twig', [
            'list' => $this->userSubscriberService->read(['order' => ['date' => 'desc']]),
        ]);
    }
}
