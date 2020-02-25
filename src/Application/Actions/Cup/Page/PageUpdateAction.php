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
                    // remove uploaded image
                    if (($uuidFile = $this->request->getParam('delete-image')) !== null && \Ramsey\Uuid\Uuid::isValid($uuidFile)) {
                        /** @var \App\Domain\Entities\File $file */
                        $file = $this->fileRepository->findOneBy(['uuid' => $uuidFile]);

                        if ($file) {
                            $file->unlink();
                            $this->entityManager->remove($file);
                            $this->entityManager->flush();
                        }
                    } else {
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
                            $item->replace($data);
                            $this->entityManager->persist($item);
                            $this->handlerFileUpload(\App\Domain\Types\FileItemType::ITEM_PAGE, $item->uuid);
                            $this->entityManager->flush();

                            if ($this->request->getParam('save', 'exit') === 'exit') {
                                return $this->response->withAddedHeader('Location', '/cup/page')->withStatus(301);
                            }

                            return $this->response->withAddedHeader('Location', $this->request->getUri()->getPath())->withStatus(301);
                        } else {
                            $this->addErrorFromCheck($check);
                        }
                    }
                }

                $files = collect($this->fileRepository->findBy([
                    'item' => \App\Domain\Types\FileItemType::ITEM_PAGE,
                    'item_uuid' => $item->uuid,
                ]));

                return $this->respondRender('cup/page/form.twig', ['item' => $item, 'files' => $files]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/page')->withStatus(301);
    }
}
