<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Domain\Exceptions\HttpRedirectException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;

class LoginAction extends AuthAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $redirect = $this->getParam('redirect', '/');

        try {
            $result = $this->auth->login(
                $this->getParam('provider', $_SESSION['auth_provider'] ?? 'BasicAuthProvider'),
                [
                    'identifier' => $this->getParam('identifier', ''),
                    'password' => $this->getParam('password', ''),
                    'code' => $this->getParam('code'),
                    'state' => $this->getParam('state'),
                ],
                [
                    'redirect' => $this->request->getUri()->getPath(),
                    'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                    'ip' => $this->getRequestRemoteIP(),
                    'comment' => 'Login via Auth',
                ]
            );

            switch ($this->isRequestJson()) {
                case true:
                    return $this->respondWithJson([
                        'user' => $result['user'],
                        'access_token' => $result['access_token'],
                        'refresh_token' => $result['refresh_token'],
                    ]);

                case false:
                default:
                    return $this->response->withAddedHeader('Location', $redirect)->withStatus(307);
            }
        } catch (UserNotFoundException $e) {
            return $this->respondWithJson(['error' => $e->getMessage()])->withStatus(404);
        } catch (WrongPasswordException $e) {
            return $this->respondWithJson(['error' => $e->getMessage()])->withStatus(400);
        }
    }
}
