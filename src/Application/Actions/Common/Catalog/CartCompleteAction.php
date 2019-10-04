<?php

namespace App\Application\Actions\Common\Catalog;

use Exception;
use Slim\Http\Response;

class CartCompleteAction extends CatalogAction
{
    /**
     * @return Response
     * @throws \Doctrine\DBAL\DBALException
     * @throws \App\Domain\Exceptions\HttpBadRequestException
     */
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))) {
            /** @var \App\Domain\Entities\Catalog\Order $order */
            $order = $this->orderRepository->findOneBy(['uuid' => $this->resolveArg('order')]);

            if (!$order->isEmpty()) {
                $products = collect($this->productRepository->findBy(['uuid' => array_keys($order->list)]));

                return $this->respondRender($this->getParameter('catalog_cart_complete_template', 'catalog.cart.complete.twig'), [
                    'order' => $order,
                    'products' => $products,
                ]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cart');
    }
}
