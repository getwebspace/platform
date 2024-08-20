<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongEmailValueException;

class UserSubscribeAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $email = $this->getParam('email', null);
        $action = $this->getParam('action', 'subscribe');
        $output = [
            'status' => 200,
            'description' => '',
        ];

        try {
            switch ($action) {
                case 'subscribe':
                    $output['status'] = 201;
                    $output['description'] = 'subscribed';
                    $this->userSubscriberService->create(['email' => $email]);
                    $this->container->get(\App\Application\PubSub::class)->publish('common:user:subscribe', 'subscribe');

                    break;

                case 'unsubscribe':
                    $output['status'] = 202;
                    $output['description'] = 'unsubscribed';
                    $subscribe = $this->userSubscriberService->read(['email' => $email]);

                    if ($subscribe) {
                        $this->userSubscriberService->delete($subscribe);
                        $this->container->get(\App\Application\PubSub::class)->publish('common:user:subscribe', 'unsubscribe');
                    }

                    break;
            }
        } catch (EmailAlreadyExistsException|UserNotFoundException|WrongEmailValueException $e) {
            $output['status'] = 304;
            $output['description'] = $e->getDescription();
        }

        if (isset($_SERVER['HTTP_REFERER'])) {
            return $this->respondWithRedirect($_SERVER['HTTP_REFERER']);
        }

        return $this->respondWithJson($output);
    }
}
