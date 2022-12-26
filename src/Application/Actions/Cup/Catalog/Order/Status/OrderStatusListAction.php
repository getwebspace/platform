<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order\Status;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderStatusListAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/catalog/order/status/index.twig', [
            'list' => $this->catalogOrderStatusService->read(),
        ]);
    }
}
