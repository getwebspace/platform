<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderListAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = $this->catalogOrderService->read();
        $users = $this->userService->read(['uuid' => $list->pluck('user_uuid')->all()]);

        return $this->respondWithTemplate('cup/catalog/order/index.twig', [
            'orders' => $list,
            'users' => $users,
        ]);
    }
}
