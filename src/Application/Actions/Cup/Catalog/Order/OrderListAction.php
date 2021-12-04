<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use const App\Application\Actions\Cup\Catalog\INVOICE_TEMPLATE;

class OrderListAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $list = $this->catalogOrderService->read([
            'limit' => 1000,
            'order' => ['date' => 'desc'],
        ]);

        return $this->respondWithTemplate('cup/catalog/order/index.twig', [
            'orders' => $list,
            'invoice' => INVOICE_TEMPLATE,
        ]);
    }
}
