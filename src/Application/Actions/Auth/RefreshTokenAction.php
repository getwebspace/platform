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
        $refresh_token = $this->getCookie('refresh_token', null);

        if ($refresh_token) {
            try {
                $token = $this->userTokenService->read([
                    'unique' => $refresh_token,
                    'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                ]);
                $expired = $token->getDate()->getTimestamp() + \App\Domain\References\Date::MONTH;

                if ($expired >= time()) {
                    $uuid = $this->getUuidString($token->getUser());

                    $access_token = $this->getAccessToken($uuid);
                    $refresh_token = $this->getRefreshToken($uuid, $token->getAgent(), $token->getIp());

                    $this->userTokenService->update($token, [
                        'unique' => $refresh_token,
                        'ip' => $this->getRequestRemoteIP(),
                        'date' => 'now',
                    ]);

                    setcookie('access_token', $access_token, time() + \App\Domain\References\Date::MONTH, '/');
                    setcookie('refresh_token', $refresh_token, time() + \App\Domain\References\Date::MONTH, '/auth');

                    $this->container->get(\App\Application\PubSub::class)->publish('auth:user:refresh-token', $token->getUser());

                    return $this
                        ->respondWithJson([
                            'access_token' => $access_token,
                            'refresh_token' => $refresh_token,
                        ])
                        ->withAddedHeader('Location', $redirect)
                        ->withStatus(308);
                }
                $this->userTokenService->delete($token);
            } catch (TokenNotFoundException $e) {
                // nothing
            }

            return $this->respondWithRedirect('/auth/logout', 307);
        }

        return $this->respondWithRedirect($redirect, 308);
    }
}
