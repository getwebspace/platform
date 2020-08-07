<?php declare(strict_types=1);

namespace App\Application\Actions\Api\User\Subscriber;

use App\Application\Actions\Api\User\UserAction;
use App\Domain\Exceptions\WrongEmailValueException;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;

class SubscribeAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            try {
                $userSubscriber = $this->userSubscriberService->create(['email' => $this->request->getParam('email')]);

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
