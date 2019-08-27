<?php

namespace App\Application\Actions\Cup\Page;

use Exception;

class PageUpdateAction extends PageAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \App\Domain\Entities\Page $item */
            $item = $this->pageRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty()) {
                if ($this->request->isPost()) {
                    $data = [
                        'uuid' => $item->uuid,
                        'title' => $this->request->getParam('title'),
                        'address' => $this->request->getParam('address'),
                        'date' => $this->request->getParam('date'),
                        'content' => $this->request->getParam('content'),
                        'type' => $this->request->getParam('type'),
                        'meta' => $this->request->getParam('meta'),
                        'template' => $this->request->getParam('template'),
                    ];

                    $check = \App\Domain\Filters\Page::check($data);

                    if ($check === true) {
                        try {
                            $item->replace($data);
                            $this->entityManager->persist($item);
                            $this->entityManager->flush();

                            if ($this->request->getParam('save', 'exit') === 'exit') {
                                return $this->response->withAddedHeader('Location', '/cup/page');
                            }
                        } catch (Exception $e) {
                            // todo nothing
                        }
                    }
                }

                return $this->respondRender('cup/page/form.twig', ['item' => $item]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/page');
    }
}
