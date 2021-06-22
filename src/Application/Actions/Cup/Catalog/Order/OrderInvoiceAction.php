<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderInvoiceAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))) {
            $order = $this->catalogOrderService->read(['uuid' => $this->resolveArg('order')]);

            if ($order) {
                $products = $this->catalogProductService->read(['uuid' => array_keys($order->getList())]);

                return $this->respondWithTemplate('cup/catalog/order/invoice.twig', [
                    'order' => $order,
                    'products' => $products,
                ]);
            }
        }

        return $this->response->withRedirect('/cup/catalog/order');
    }
}
