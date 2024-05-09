<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\Subscriber;

use App\Application\Actions\Cup\User\UserAction;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\WrongEmailValueException;

class CreateAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            try {
                $this->userSubscriberService->create([
                    'email' => $this->getParam('email'),
                ]);
            } catch (EmailAlreadyExistsException|WrongEmailValueException $e) {
                $this->addError('email', $e->getMessage());
            }
        }

        return $this->respondWithRedirect('/cup/user/subscriber');
    }
}
