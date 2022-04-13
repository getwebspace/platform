<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Page;

class PageDeleteAction extends PageAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $page = $this->pageService->read([
                'uuid' => $this->resolveArg('uuid'),
            ]);

            if ($page) {
                $this->pageService->delete($page);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:page:delete', $page);
            }
        }

        return $this->respondWithRedirect('/cup/page');
    }
}
