<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;

class OrderListAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $catalogOrderService = CatalogOrderService::getWithContainer($this->container);
        $list = $catalogOrderService->read();

        return $this->respondWithTemplate('cup/catalog/order/index.twig', ['orders' => $list]);
    }
}
