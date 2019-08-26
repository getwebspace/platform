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
                        try {
                            $item->replace($data);
                            $this->entityManager->persist($item);
                            $this->entityManager->flush();

                            return $this->response->withAddedHeader('Location', '/cup/publication');
                        } catch (Exception $e) {
                            // todo nothing
                        }
                    }
                }

                $list = collect($this->categoryRepository->findAll());

                return $this->respondRender('cup/publication/form.twig', ['list' => $list, 'item' => $item]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/publication');
    }
}
