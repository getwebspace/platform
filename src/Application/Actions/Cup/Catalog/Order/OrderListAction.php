<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderListAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/catalog/order/index.twig', [
            'list' => $this->catalogOrderService->read([
                'order' => ['date' => 'desc'],
            ]),
        ]);
    }
}
