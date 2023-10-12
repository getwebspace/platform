<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

class UserViewAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid')) {
            $user = $this->userService->read(['uuid' => $this->resolveArg('uuid')]);

            if ($user) {
                return $this->respondWithTemplate('cup/user/view.twig', [
                    'item' => $user
                ]);
            }
        }

        return $this->respondWithRedirect('/cup/user');
    }
}
