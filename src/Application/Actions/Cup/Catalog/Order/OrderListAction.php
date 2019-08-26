<?php

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderListAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->orderRepository->findAll());

        return $this->respondRender('cup/catalog/order/index.twig', ['orders' => $list]);
    }
}
