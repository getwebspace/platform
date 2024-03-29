<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Page;

class PageListAction extends PageAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/page/index.twig', [
            'list' => $this->pageService->read(['order' => ['title' => 'asc']]),
        ]);
    }
}
