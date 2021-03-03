<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Application\Actions\Cup\User\UserAction;

class ForbiddenPageAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respond('p403.twig')->withStatus(403);
    }
}
