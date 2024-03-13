<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Domain\Service\User\Exception\TokenNotFoundException;
use App\Domain\Traits\SecurityTrait;

class LogoutAction extends AuthAction
{
    use SecurityTrait;

    protected function action(): \Slim\Psr7\Response
    {
        $redirect = $this->getParam('redirect', '/');
        $refresh_token = $this->getParam('token', $this->getCookie('refresh_token', null));

        if ($refresh_token) {
            try {
                /** @var \App\Domain\Models\UserToken $token */
                $token = $this->userTokenService->read(['unique' => $refresh_token]);

                /** @var \App\Domain\Models\User $user */
                $user = $token->user;

                $this->userTokenService->delete($token);

                $this->container->get(\App\Application\PubSub::class)->publish('auth:user:logout', $user);
            } catch (TokenNotFoundException $e) {
                // nothing
            } finally {
                setcookie('access_token', '', time(), '/');
                setcookie('refresh_token', '', time(), '/auth');
            }
        }

        switch ($this->isRequestJson()) {
            case true:
                return $this->respondWithJson();

            case false:
            default:
                return $this->response->withAddedHeader('Location', $redirect)->withStatus(307);
        }
    }
}
