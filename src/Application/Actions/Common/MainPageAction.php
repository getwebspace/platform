<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Application\Actions\Action;

class MainPageAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondWithTemplate($this->getParameter('common_template', 'main.twig'));
    }
}
