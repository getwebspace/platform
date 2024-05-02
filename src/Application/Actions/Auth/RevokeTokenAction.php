<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Domain\Traits\UseSecurity;

class RevokeTokenAction extends AuthAction
{
    use UseSecurity;

    protected function action(): \Slim\Psr7\Response
    {
        $redirect = $this->getParam('redirect', '/');
        $refresh_token = $this->getCookie('refresh_token', null);

        if ($refresh_token) {
            /** @var \App\Domain\Models\User $user */
            $user = $this->request->getAttribute('user', false);

            foreach ($user->tokens()->where('unique', '!=', $refresh_token)->get() as $token) {
                $this->userTokenService->delete($token);
            }

            $this->container->get(\App\Application\PubSub::class)->publish('auth:user:revoke-token', $user);
        }

        return $this->respondWithRedirect($redirect, 307);
    }
}
