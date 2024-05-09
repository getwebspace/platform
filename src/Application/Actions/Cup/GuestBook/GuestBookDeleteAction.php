<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\GuestBook;

use App\Domain\Service\GuestBook\Exception\EntryNotFoundException;

class GuestBookDeleteAction extends GuestBookAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $entry = $this->guestBookService->read([
                    'uuid' => $this->resolveArg('uuid'),
                ]);

                if ($entry) {
                    $this->guestBookService->delete($entry);

                    $this->container->get(\App\Application\PubSub::class)->publish('cup:guestbook:delete', $entry);
                }
            } catch (EntryNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/guestbook');
    }
}
