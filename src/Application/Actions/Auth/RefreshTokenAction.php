<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Domain\Service\User\Exception\TokenNotFoundException;
use App\Domain\Traits\SecurityTrait;

class RefreshTokenAction extends AuthAction
{
    use SecurityTrait;

    protected function action(): \Slim\Psr7\Response
    {
        $redirect = $this->getParam('redirect', '/');
        $refresh_token = $this->getParam('token', $this->getCookie('refresh_token', null));
        $output = [];

        if ($refresh_token) {
            try {
                $token = $this->userTokenService->read([
                    'unique' => $refresh_token,
                    'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                ]);
                $expired = $token->getDate()->getTimestamp() + \App\Domain\References\Date::MONTH;

                if ($expired >= time()) {
                    $tokens = $this->getTokenPair([
                        'user' => $token->getUser(),
                        'user_token' => $token,
                        'ip' => $this->getRequestRemoteIP(),
                    ]);

                    setcookie('access_token', $tokens['access_token'], time() + \App\Domain\References\Date::MONTH, '/');
                    setcookie('refresh_token', $tokens['refresh_token'], time() + \App\Domain\References\Date::MONTH, '/auth');

                    $this->container->get(\App\Application\PubSub::class)->publish('auth:user:refresh-token', $token->getUser());

                    $output = [
                        'access_token' => $tokens['access_token'],
                        'refresh_token' => $tokens['refresh_token'],
                    ];
                } else {
                    $this->userTokenService->delete($token);
                }
            } catch (TokenNotFoundException $e) {
                $redirect = '/auth/logout';
            }
        } else {
            $redirect = '/auth/logout';
        }

        switch ($this->isRequestJson()) {
            case true:
                return $this->respondWithJson($output);

            case false:
            default:
                return $this->response->withAddedHeader('Location', $redirect)->withStatus(308);
        }
    }
}
