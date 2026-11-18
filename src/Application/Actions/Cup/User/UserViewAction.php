<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

class UserViewAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                return $this->respondWithTemplate('cup/user/view.twig', [
                    'item' => $this->userService->read(['uuid' => $this->resolveArg('uuid')]),
                ]);
            } catch (\App\Domain\Service\User\Exception\UserNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/user');
    }
}
