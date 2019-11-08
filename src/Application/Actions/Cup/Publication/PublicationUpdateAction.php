<?php

namespace App\Application\Actions\Cup\Publication;

use Exception;

class PublicationUpdateAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \App\Domain\Entities\Publication $item */
            $item = $this->publicationRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

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
                            'category' => $this->request->getParam('category'),
                            'content' => $this->request->getParam('content'),
                            'poll' => $this->request->getParam('poll'),
                            'meta' => $this->request->getParam('meta'),
                        ];

                        $check = \App\Domain\Filters\Publication::check($data);

                        if ($check === true) {
                            $item->replace($data);
                            $this->entityManager->persist($item);
                            $this->handlerFileUpload(\App\Domain\Types\FileItemType::ITEM_PUBLICATION, $item->uuid);
                            $this->entityManager->flush();

                            if ($this->request->getParam('save', 'exit') === 'exit') {
                                return $this->response->withAddedHeader('Location', '/cup/publication')->withStatus(301);
                            }
                        } else {
                            $this->addErrorFromCheck($check);
                        }
                    }
                }

                $list = collect($this->categoryRepository->findAll());
                $files = collect($this->fileRepository->findBy([
                    'item' => \App\Domain\Types\FileItemType::ITEM_PUBLICATION,
                    'item_uuid' => $item->uuid,
                ]));

                return $this->respondRender('cup/publication/form.twig', ['list' => $list, 'item' => $item, 'files' => $files]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/publication')->withStatus(301);
    }
}
