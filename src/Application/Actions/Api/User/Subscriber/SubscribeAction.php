<?php declare(strict_types=1);

namespace App\Application\Actions\Api\User\Subscriber;

use App\Application\Actions\Cup\User\UserAction;
use App\Domain\Exceptions\WrongEmailValueException;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\SubscriberService as UserSubscriberService;

class SubscribeAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            try {
                $userSubscriberService = UserSubscriberService::getFromContainer($this->container);
                $userSubscriber = $userSubscriberService->create(['email' => $this->request->getParam('email')]);

                if ($userSubscriber) {
                    return $this->response->withStatus(201);
                }
            } catch (WrongEmailValueException|EmailAlreadyExistsException $e) {
                // ignore
            }
        }

        return $this->response->withStatus(204);
    }
}
