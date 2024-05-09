<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Page;

use App\Domain\Service\Page\Exception\PageNotFoundException;

class PageDeleteAction extends PageAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $page = $this->pageService->read([
                    'uuid' => $this->resolveArg('uuid'),
                ]);

                if ($page) {
                    $this->pageService->delete($page);

                    $this->container->get(\App\Application\PubSub::class)->publish('cup:page:delete', $page);
                }
            } catch (PageNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/page');
    }
}
