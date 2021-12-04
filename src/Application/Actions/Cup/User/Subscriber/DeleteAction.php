<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\Subscriber;

use App\Application\Actions\Cup\User\UserAction;
use App\Domain\Service\User\Exception\UserNotFoundException;

class DeleteAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $userSubscriber = $this->userSubscriberService->read(['uuid' => $this->resolveArg('uuid')]);

            if ($userSubscriber) {
                try {
                    $this->userSubscriberService->delete($userSubscriber);
                } catch (UserNotFoundException $e) {
                    // ignore
                }
            }
        }

        return $this->respondWithRedirect('/cup/user/subscriber');
    }
}
