<?php

namespace Application\Actions\Cup\Catalog\Order;

use Application\Actions\Cup\Catalog\CatalogAction;
use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class OrderListAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->orderRepository->findAll());

        return $this->respondRender('cup/catalog/order/index.twig', ['orders' => $list]);
    }
}
