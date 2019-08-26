<?php

namespace App\Application\Actions\Cup;

use App\Application\Actions\Action;

class DocsPageAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondRender('cup/docs/index.twig');
    }
}
