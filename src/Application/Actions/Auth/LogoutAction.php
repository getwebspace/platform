<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

class LogoutAction extends AuthAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $redirect = $this->getParam('redirect', '/');
        $refresh_token = $this->getParam('token', $this->getCookie('refresh_token', null));

        if ($refresh_token) {
            $this->auth->logout(
                $this->getParam('provider', $_SESSION['auth_provider'] ?? 'BasicAuthProvider'),
                $refresh_token,
            );

            @setcookie('access_token', '', time(), '/');
            @setcookie('refresh_token', '', time(), '/auth');
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
