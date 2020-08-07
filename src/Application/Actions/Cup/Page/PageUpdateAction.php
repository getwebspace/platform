<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Page;

use App\Domain\Service\Page\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Page\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Page\PageService;

class PageUpdateAction extends PageAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $page = $this->pageService->read(['uuid' => $this->resolveArg('uuid')]);

            if ($page) {
                if ($this->request->isPost()) {
                    try {
                        $page = $this->pageService->update($page, [
                            'title' => $this->request->getParam('title'),
                            'address' => $this->request->getParam('address'),
                            'date' => $this->request->getParam('date'),
                            'content' => $this->request->getParam('content'),
                            'type' => $this->request->getParam('type'),
                            'meta' => $this->request->getParam('meta'),
                            'template' => $this->request->getParam('template'),
                        ]);
                        $page = $this->handlerEntityFiles($page);

                        switch (true) {
                            case $this->request->getParam('save', 'exit') === 'exit':
                                return $this->response->withRedirect('/cup/page');
                            default:
                                return $this->response->withRedirect('/cup/page/' . $page->getUuid() . '/edit');
                        }
                    } catch (TitleAlreadyExistsException $e) {
                        $this->addError('title', $e->getMessage());
                    } catch (AddressAlreadyExistsException $e) {
                        $this->addError('address', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate('cup/page/form.twig', ['item' => $page]);
            }
        }

        return $this->response->withRedirect('/cup/page');
    }
}
