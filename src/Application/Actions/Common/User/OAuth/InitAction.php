<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User\OAuth;

use App\Application\Actions\Common\User\UserAction;

class InitAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $provider = $this->getOAuthService()->getProvider($this->resolveArg('provider'));

        return $this->respondWithRedirect($provider->makeAuthUrl());
    }
}
