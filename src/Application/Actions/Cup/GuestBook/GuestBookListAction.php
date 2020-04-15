<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\GuestBook;

class GuestBookListAction extends GuestBookAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->gbookRepository->findAll());

        return $this->respondWithTemplate('cup/guestbook/index.twig', ['list' => $list]);
    }
}
