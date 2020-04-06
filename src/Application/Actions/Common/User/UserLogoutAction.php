<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

class UserLogoutAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        setcookie('uuid', '-1', time(), '/');
        setcookie('session', '-1', time(), '/');

        return $this->response->withAddedHeader('Location', '/')->withStatus(301);
    }
}
