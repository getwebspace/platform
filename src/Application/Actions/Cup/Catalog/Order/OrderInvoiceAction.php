<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use const App\Application\Actions\Cup\Catalog\INVOICE_TEMPLATE;

class OrderInvoiceAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))) {
            $order = $this->catalogOrderService->read(['uuid' => $this->resolveArg('order')]);

            if ($order) {
                return $this->respondWithTemplate('cup/catalog/order/invoice.twig', [
                    'order' => $order,
                    'invoice' => INVOICE_TEMPLATE,
                ]);
            }
        }

        return $this->response->withRedirect('/cup/catalog/order');
    }
}
