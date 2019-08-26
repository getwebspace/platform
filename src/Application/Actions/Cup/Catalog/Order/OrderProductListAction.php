<?php

namespace Application\Actions\Cup\Catalog\Order;

use Application\Actions\Cup\Catalog\CatalogAction;

class OrderProductListAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->productRepository->findBy([
            'status' => \Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
        ]));

        return $this->respondRender('cup/catalog/order/product-list.twig', ['products' => $list]);
    }
}
