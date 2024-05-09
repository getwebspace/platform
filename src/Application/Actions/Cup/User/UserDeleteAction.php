<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use App\Domain\Service\User\Exception\UserNotFoundException;

class UserDeleteAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $user = $this->userService->read([
                    'uuid' => $this->resolveArg('uuid'),
                ]);

                if ($user) {
                    $this->userService->delete($user);

                    $this->container->get(\App\Application\PubSub::class)->publish('cup:user:delete', $user);
                }
            } catch (UserNotFoundException $e) {
                // nothing
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/user')->withStatus(301);
    }
}
