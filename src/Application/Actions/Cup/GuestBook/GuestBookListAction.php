<?php

namespace App\Application\Actions\Cup\GuestBook;

class GuestBookListAction extends GuestBookAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->gbookRepository->findAll());

        return $this->respondRender('cup/guestbook/index.twig', ['list' => $list]);
    }
}
