<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderInvoiceAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))) {
            $order = $this->catalogOrderService->read(['uuid' => $this->resolveArg('order')]);

            if ($order) {
                return $this->respondWithTemplate('cup/catalog/order/invoice.twig', [
                    'order' => $order,
                    'invoice' => $this->parameter('catalog_invoice', ''),
                ]);
            }
        }

        return $this->respondWithRedirect('/cup/catalog/order');
    }
}
