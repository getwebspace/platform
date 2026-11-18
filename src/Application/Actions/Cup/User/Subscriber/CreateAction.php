<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\Subscriber;

use App\Application\Actions\Cup\User\UserAction;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
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
            } catch (EmailAlreadyExistsException|MissingUniqueValueException|WrongEmailValueException $e) {
                $this->addError('email', $e->getMessage());

                // back to the list with the reason, a redirect would lose it
                return $this->respondWithTemplate('cup/user/subscriber/index.twig', [
                    'list' => $this->userSubscriberService->read(['order' => ['date' => 'desc']]),
                ]);
            }
        }

        return $this->respondWithRedirect('/cup/user/subscriber');
    }
}
