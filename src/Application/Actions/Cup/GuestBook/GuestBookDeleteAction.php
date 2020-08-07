<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\GuestBook;

class GuestBookDeleteAction extends GuestBookAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $this->guestBookService->delete($this->resolveArg('uuid'));
        }

        return $this->response->withRedirect('/cup/guestbook');
    }
}
