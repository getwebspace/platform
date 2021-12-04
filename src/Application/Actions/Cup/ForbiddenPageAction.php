<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Application\Actions\Cup\User\UserAction;

class ForbiddenPageAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/auth/forbidden.twig')->withStatus(403);
    }
}
