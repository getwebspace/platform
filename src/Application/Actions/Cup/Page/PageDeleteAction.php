<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Page;

use App\Domain\Service\Page\PageService;

class PageDeleteAction extends PageAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $pageService = PageService::getWithContainer($this->container);
            $pageService->delete($this->resolveArg('uuid'));
        }

        return $this->response->withRedirect('/cup/page');
    }
}
