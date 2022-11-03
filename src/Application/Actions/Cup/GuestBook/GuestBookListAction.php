<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\GuestBook;

class GuestBookListAction extends GuestBookAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/guestbook/index.twig', [
            'list' => $this->guestBookService->read(['order' => ['date' => 'desc']]),
        ]);
    }
}
