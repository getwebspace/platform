<?php

namespace Application\Actions\Cup;

use Application\Actions\Action;
use DateTime;
use Exception;
use Psr\Container\ContainerInterface;

class DocsPageAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondRender('cup/docs/index.twig');
    }
}
