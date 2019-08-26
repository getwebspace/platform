<?php

namespace App\Application\Actions\Cup\Page;

class PageListAction extends PageAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->pageRepository->findAll());

        return $this->respondRender('cup/page/index.twig', ['list' => $list]);
    }
}
