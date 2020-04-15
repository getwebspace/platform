<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Page;

class PageListAction extends PageAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->pageRepository->findAll());

        return $this->respondWithTemplate('cup/page/index.twig', ['list' => $list]);
    }
}
