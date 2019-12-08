<?php

namespace App\Application\Actions\Cup\GuestBook;

use Exception;

class GuestBookUpdateAction extends GuestBookAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \App\Domain\Entities\GuestBook $item */
            $item = $this->gbookRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty()) {
                if ($this->request->isPost()) {
                    $data = [
                        'uuid' => $item->uuid,
                        'name' => $this->request->getParam('name'),
                        'email' => $this->request->getParam('email'),
                        'message' => $this->request->getParam('message'),
                        'response' => $this->request->getParam('response'),
                        'date' => $this->request->getParam('date'),
                        'status' => $this->request->getParam('status'),
                    ];

                    $check = \App\Domain\Filters\GuestBook::check($data);

                    if ($check === true) {
                        $item->replace($data);
                        $this->entityManager->persist($item);
                        $this->entityManager->flush();

                        if ($this->request->getParam('save', 'exit') === 'exit') {
                            return $this->response->withAddedHeader('Location', '/cup/guestbook')->withStatus(301);
                        }
                    } else {
                        $this->addErrorFromCheck($check);
                    }
                }

                return $this->respondRender('cup/guestbook/form.twig', ['item' => $item]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/guestbook')->withStatus(301);
    }
}
