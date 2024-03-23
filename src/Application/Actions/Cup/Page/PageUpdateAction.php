<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Page;

use App\Domain\Service\Page\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Page\Exception\MissingTitleValueException;
use App\Domain\Service\Page\Exception\PageNotFoundException;
use App\Domain\Service\Page\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Page\Exception\WrongTitleValueException;

class PageUpdateAction extends PageAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $page = $this->pageService->read([
                    'uuid' => $this->resolveArg('uuid'),
                ]);

                if ($this->isPost()) {
                    try {
                        $page = $this->pageService->update($page, [
                            'title' => $this->getParam('title'),
                            'address' => $this->getParam('address'),
                            'date' => $this->getParam('date', 'now'),
                            'content' => $this->getParam('content'),
                            'type' => $this->getParam('type'),
                            'meta' => $this->getParam('meta'),
                            'template' => $this->getParam('template'),
                        ]);
                        $page = $this->processEntityFiles($page);

                        $this->container->get(\App\Application\PubSub::class)->publish('cup:page:edit', $page);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->respondWithRedirect('/cup/page');

                            default:
                                return $this->respondWithRedirect('/cup/page/' . $page->uuid . '/edit');
                        }
                    } catch (MissingTitleValueException|WrongTitleValueException|TitleAlreadyExistsException $e) {
                        $this->addError('title', $e->getMessage());
                    } catch (AddressAlreadyExistsException $e) {
                        $this->addError('address', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate('cup/page/form.twig', [
                    'item' => $page,
                ]);
            } catch (PageNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/page');
    }
}
