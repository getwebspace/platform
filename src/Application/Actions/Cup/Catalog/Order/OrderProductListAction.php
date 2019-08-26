<?php

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderProductListAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->productRepository->findBy([
            'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
        ]));

        return $this->respondRender('cup/catalog/order/product-list.twig', ['products' => $list]);
    }
}
