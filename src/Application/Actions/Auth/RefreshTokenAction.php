<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Domain\Service\User\Exception\TokenNotFoundException;

class RefreshTokenAction extends AuthAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $redirect = $this->getRedirectParam();
        $refresh_token = $this->getParam('token', $this->getCookie('refresh_token'));

        $result = [];

        if ($refresh_token) {
            try {
                $result = $this->auth->refresh(
                    $this->getParam('provider', $_SESSION['auth_provider'] ?? 'BasicAuthProvider'),
                    $refresh_token,
                    [
                        'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                        'ip' => $this->getRequestRemoteIP(),
                    ]
                );

                $this->setAuthCookies($result);
            } catch (TokenNotFoundException $e) {
                $redirect = '/auth/logout';
            }
        } else {
            $redirect = '/auth/logout';
        }

        switch ($this->isRequestJson()) {
            case true:
                return $this->respondWithJson($result);

            case false:
            default:
                return $this->response->withAddedHeader('Location', $redirect)->withStatus(308);
        }
    }
}
