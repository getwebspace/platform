<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

class UserRevokeTokenAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $params = [
            'redirect' => $this->getParam('redirect', '/user/profile'),
            'uuid' => $this->getParam('uuid'),
        ];

        return $this->respondWithRedirect('/auth/revoke?' . urldecode(http_build_query($params)));
    }
}
