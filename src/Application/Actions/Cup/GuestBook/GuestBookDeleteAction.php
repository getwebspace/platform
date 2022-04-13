<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\GuestBook;

class GuestBookDeleteAction extends GuestBookAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $entry = $this->guestBookService->read([
                'uuid' => $this->resolveArg('uuid'),
            ]);

            if ($entry) {
                $this->guestBookService->delete($entry);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:guestbook:delete', $entry);
            }
        }

        return $this->respondWithRedirect('/cup/guestbook');
    }
}
