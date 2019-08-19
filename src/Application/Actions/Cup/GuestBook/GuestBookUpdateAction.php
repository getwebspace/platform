<?php

namespace Application\Actions\Cup\GuestBook;

use Exception;

class GuestBookUpdateAction extends GuestBookAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \Domain\Entities\GuestBook $item */
            $item = $this->gbookRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty()) {
                if ($this->request->isPost()) {
                    $data = [
                        'uuid' => $item->uuid,
                        'name' => $this->request->getParam('name'),
                        'email' => $this->request->getParam('email'),
                        'message' => $this->request->getParam('message'),
                        'date' => $this->request->getParam('date'),
                        'status' => $this->request->getParam('status'),
                    ];

                    $check = \Domain\Filters\GuestBook::check($data);

                    if ($check === true) {
                        try {
                            $item->replace($data);
                            $this->entityManager->persist($item);
                            $this->entityManager->flush();

                            return $this->response->withAddedHeader('Location', '/cup/guestbook');
                        } catch (Exception $e) {
                            // todo nothing
                        }
                    }
                }

                return $this->respondRender('cup/guestbook/form.twig', ['item' => $item]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/guestbook');
    }
}
