<?php

namespace Application\Actions\Cup\GuestBook;

use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class GuestBookListAction extends GuestBookAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->gbookRepository->findAll());

        return $this->respondRender('cup/guestbook/index.twig', ['list' => $list]);
    }
}
