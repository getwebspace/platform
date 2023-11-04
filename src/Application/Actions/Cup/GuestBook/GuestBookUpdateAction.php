<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\GuestBook;

use App\Domain\Service\GuestBook\Exception\EntryNotFoundException;

class GuestBookUpdateAction extends GuestBookAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $entry = $this->guestBookService->read([
                    'uuid' => $this->resolveArg('uuid'),
                ]);

                if ($this->isPost()) {
                    $entry = $this->guestBookService->update($entry, [
                        'name' => $this->getParam('name'),
                        'email' => $this->getParam('email'),
                        'message' => $this->getParam('message'),
                        'response' => $this->getParam('response'),
                        'date' => $this->getParam('date'),
                        'status' => $this->getParam('status'),
                    ]);

                    $this->container->get(\App\Application\PubSub::class)->publish('cup:guestbook:edit', $entry);

                    switch (true) {
                        case $this->getParam('save', 'exit') === 'exit':
                            return $this->respondWithRedirect('/cup/guestbook');

                        default:
                            return $this->respondWithRedirect('/cup/guestbook/' . $entry->getUuid() . '/edit');
                    }
                }

                return $this->respondWithTemplate('cup/guestbook/form.twig', [
                    'item' => $entry,
                ]);
            } catch (EntryNotFoundException $e) {
                // nothing
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/guestbook')->withStatus(301);
    }
}
