<?php declare(strict_types=1);

namespace App\Application\Actions\Common\Catalog;

use App\Domain\Service\Catalog\Exception\OrderNotFoundException;
use Slim\Http\Response;

class CartDoneAction extends CatalogAction
{
    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \App\Domain\Exceptions\HttpBadRequestException
     *
     * @return Response
     */
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))) {
            try {
                $order = $this->catalogOrderService->read(['uuid' => $this->resolveArg('order')]);

                if ($order) {
                    $products = $this->catalogProductService->read(['uuid' => array_keys($order->getList())]);

                    return $this->respond($this->parameter('catalog_cart_complete_template', 'catalog.cart.complete.twig'), [
                        'order' => $order,
                        'products' => $products,
                    ]);
                }
            } catch (OrderNotFoundException $e) {
                // skip, redirect to cart
            }
        }

        return $this->response->withRedirect('/cart');
    }
}
