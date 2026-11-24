<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Domain\Service\User\Exception\TokenNotFoundException;

class RefreshTokenAction extends AuthAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $redirect = $this->getRedirectParam();
        $refresh_token = $this->getParam('token', $this->getCookie('refresh_token'));

        // the authorization middleware sends a request here with the address it was going to:
        // the answer to such a request is the way back, whatever format the caller speaks
        $raw = $this->getParam('redirect');
        $back = is_string($raw) && $this->isLocalPath($raw) && !str_starts_with($raw, '/auth/refresh-token') ? $raw : null;

        $result = [];
        $failed = false;

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

                // null: the session expired or its owner can no longer sign in
                if ($result === null) {
                    $failed = true;
                } else {
                    $this->setAuthCookies($result);
                }
            } catch (TokenNotFoundException $e) {
                $failed = true;
            }
        } else {
            $failed = true;
        }

        if ($failed) {
            // dead cookies would send every next request here again
            $result = [];
            $this->clearAuthCookies();
        }

        if ($back !== null) {
            // the visitor goes on to the page as a guest if the session could not be renewed
            return $this->response->withAddedHeader('Location', $back)->withStatus(308);
        }

        switch ($this->isRequestJson()) {
            case true:
                return $this->respondWithJson($result);

            case false:
            default:
                return $this->response->withAddedHeader('Location', $failed ? '/auth/logout' : $redirect)->withStatus(308);
        }
    }
}
