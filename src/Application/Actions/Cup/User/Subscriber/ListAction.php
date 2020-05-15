<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\Subscriber;

use App\Application\Actions\Cup\User\UserAction;
use App\Domain\Service\User\SubscriberService as UserSubscriberService;

class ListAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        $userSubscriberService = UserSubscriberService::getFromContainer($this->container);

        return $this->respondWithTemplate('cup/user/subscriber/index.twig', ['list' => $userSubscriberService->read()]);
    }
}
