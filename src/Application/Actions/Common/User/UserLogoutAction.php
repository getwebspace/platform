<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

class UserLogoutAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        /** @var \App\Domain\Entities\User $user */
        $user = $this->request->getAttribute('user', false);

        if ($user) {
            /** @var \App\Domain\Entities\User\Token $token */
            $token = $this->request->getAttribute('user-token', false);

            if ($token) {
                $this->userTokenService->delete($token);
            }

            setcookie('access_token', '', time(), '/');
            setcookie('refresh_token', '', time(), '/');

            $this->container->get(\App\Application\PubSub::class)->publish('common:user:logout', $user);
        }

        return $this->respondWithRedirect('/');
    }
}
