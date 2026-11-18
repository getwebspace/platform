<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\GuestBook;

use App\Domain\Service\GuestBook\Exception\EntryNotFoundException;
use App\Domain\Service\GuestBook\Exception\MissingEmailValueException;
use App\Domain\Service\GuestBook\Exception\MissingMessageValueException;
use App\Domain\Service\GuestBook\Exception\MissingNameValueException;
use App\Domain\Service\GuestBook\Exception\WrongEmailValueException;

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
                    try {
                        $entry = $this->guestBookService->update($entry, [
                            'name' => $this->getParam('name'),
                            'email' => $this->getParam('email'),
                            'message' => $this->getParam('message'),
                            'response' => $this->getParam('response'),
                            'date' => $this->getParam('date', 'now'),
                            'status' => $this->getParam('status'),
                        ]);

                        $this->container->get(\App\Application\PubSub::class)->publish('cup:guestbook:edit', $entry);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->respondWithRedirect('/cup/guestbook');

                            default:
                                return $this->respondWithRedirect('/cup/guestbook/' . $entry->uuid . '/edit');
                        }
                    } catch (MissingNameValueException $e) {
                        $this->addError('name', $e->getMessage());
                    } catch (MissingEmailValueException|WrongEmailValueException $e) {
                        $this->addError('email', $e->getMessage());
                    } catch (MissingMessageValueException $e) {
                        $this->addError('message', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate('cup/guestbook/form.twig', [
                    'item' => $entry,
                ]);
            } catch (EntryNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/guestbook');
    }
}
