<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\User\UserService;

class OrderListAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $catalogOrderService = CatalogOrderService::getWithContainer($this->container);
        $userService = UserService::getWithContainer($this->container);

        $list = $catalogOrderService->read();
        $users = $userService->read(['uuid' => $list->pluck('user_uuid')->all()]);

        return $this->respondWithTemplate('cup/catalog/order/index.twig', [
            'orders' => $list,
            'users' => $users,
        ]);
    }
}
