<?php

namespace Application\Actions\Common;

use Application\Actions\Action;

class MainPageAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondRender('main.twig');
    }
}
