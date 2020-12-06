<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\GuestBook;

class GuestBookUpdateAction extends GuestBookAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $entry = $this->guestBookService->read(['uuid' => $this->resolveArg('uuid')]);

            if ($entry) {
                if ($this->request->isPost()) {
                    $entry = $this->guestBookService->update($entry, [
                        'name' => $this->request->getParam('name'),
                        'email' => $this->request->getParam('email'),
                        'message' => $this->request->getParam('message'),
                        'response' => $this->request->getParam('response'),
                        'date' => $this->request->getParam('date'),
                        'status' => $this->request->getParam('status'),
                    ]);

                    switch (true) {
                        case $this->request->getParam('save', 'exit') === 'exit':
                            return $this->response->withRedirect('/cup/guestbook');
                        default:
                            return $this->response->withRedirect('/cup/guestbook/' . $entry->getUuid() . '/edit');
                    }
                }

                return $this->respondWithTemplate('cup/guestbook/form.twig', ['item' => $entry]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/guestbook')->withStatus(301);
    }
}
