<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

class UserRevokeTokenAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithRedirect('/auth/revoke?redirect=' . $this->getParam('redirect', '/user/profile'));
    }
}
