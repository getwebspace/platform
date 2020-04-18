<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Domain\AbstractAction;

class MainPageAction extends AbstractAction
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondWithTemplate($this->getParameter('common_template', 'main.twig'));
    }
}
