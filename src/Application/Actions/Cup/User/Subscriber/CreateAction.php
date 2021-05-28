<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\Subscriber;

use App\Application\Actions\Cup\User\UserAction;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\WrongEmailValueException;

class CreateAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            try {
                $this->userSubscriberService->create([
                    'email' => $this->request->getParam('email'),
                ]);
            } catch (WrongEmailValueException | EmailAlreadyExistsException $e) {
                $this->addError('email', $e->getMessage());
            }
        }

        return $this->response->withRedirect('/cup/user/subscriber');
    }
}
