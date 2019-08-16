<?php

namespace Application\Actions\Cup\Page;

use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class PageListAction extends PageAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->pageRepository->findAll());

        return $this->respondRender('cup/page/index.twig', ['list' => $list]);
    }
}
