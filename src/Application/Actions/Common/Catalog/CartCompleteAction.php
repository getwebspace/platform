<?php declare(strict_types=1);

namespace App\Application\Actions\Common\Catalog;

use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use Slim\Http\Response;

class CartCompleteAction extends CatalogAction
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
            $catalogOrderService = CatalogOrderService::getWithContainer($this->container);
            $order = $catalogOrderService->read(['uuid' => $this->resolveArg('order')]);

            if ($order) {
                $catalogProductService = CatalogProductService::getWithContainer($this->container);
                $products = $catalogProductService->read(['uuid' => array_keys($order->getList())]);

                return $this->respondWithTemplate($this->getParameter('catalog_cart_complete_template', 'catalog.cart.complete.twig'), [
                    'order' => $order,
                    'products' => $products,
                ]);
            }
        }

        return $this->response->withRedirect('/cart');
    }
}
