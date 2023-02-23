<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Actions\Common\User\UserAction;
use App\Domain\Service\User\Exception\TokenNotFoundException;
use App\Domain\Service\User\TokenService as UserTokenService;
use App\Domain\Service\User\UserService;
use App\Domain\Traits\SecurityTrait;
use DateTime;
use Psr\Container\ContainerInterface;

class RevokeTokenAction extends UserAction
{
    use SecurityTrait;

    protected function action(): \Slim\Psr7\Response
    {
        $redirect = $this->getParam('redirect', '/');
        $refresh_token = $this->getCookie('refresh_token', null);

        if ($refresh_token) {
            /** @var \App\Domain\Entities\User $user */
            $user = $this->request->getAttribute('user', false);

            foreach ($user->getTokens()->whereNotIn('unique', $refresh_token) as $token) {
                $this->userTokenService->delete($token);
            }
        }

        return $this->respondWithRedirect($redirect, 307);
    }
}
