<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Domain\Models\User;

class RevokeTokenAction extends AuthAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $redirect = $this->getParam('redirect', '/');
        $refresh_token = $this->getParam('token', $this->getCookie('refresh_token'));
        $uuid = $this->getParam('uuid');

        if ($refresh_token) {
            $this->auth->revoke(
                $this->getParam('provider', $_SESSION['auth_provider'] ?? 'BasicAuthProvider'),
                $refresh_token,
                $uuid
            );

            /** @var User $user */
            if (($user = $this->request->getAttribute('user', false)) !== false && $user->tokens->isEmpty()) {
                $redirect = '/auth/logout';
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
