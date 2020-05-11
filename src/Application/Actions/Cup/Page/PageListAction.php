<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Page;

use App\Domain\Service\Page\PageService;

class PageListAction extends PageAction
{
    protected function action(): \Slim\Http\Response
    {
        $pageService = PageService::getFromContainer($this->container);
        $list = $pageService->read();

        return $this->respondWithTemplate('cup/page/index.twig', ['list' => $list]);
    }
}
