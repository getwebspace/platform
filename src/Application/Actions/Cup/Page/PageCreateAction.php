<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Page;

use App\Domain\Service\Page\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Page\Exception\MissingTitleValueException;
use App\Domain\Service\Page\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Page\Exception\WrongTitleValueException;

class PageCreateAction extends PageAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            try {
                $page = $this->pageService->create([
                    'title' => $this->getParam('title'),
                    'address' => $this->getParam('address'),
                    'date' => $this->getParam('date', 'now'),
                    'content' => $this->getParam('content'),
                    'type' => $this->getParam('type'),
                    'meta' => $this->getParam('meta'),
                    'template' => $this->getParam('template'),
                ]);
                $page = $this->processEntityFiles($page);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:page:create', $page);

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

        return $this->respondWithTemplate('cup/page/form.twig');
    }
}
