<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderCreateAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            $user_uuid = $this->getParam('user_uuid');

            // todo try/catch
            $order = $this->catalogOrderService->create([
                'user' => $user_uuid ? $this->userService->read(['uuid' => $user_uuid]) : null,
                'delivery' => $this->getParam('delivery'),
                'list' => $this->getParam('list', []),
                'phone' => $this->getParam('phone'),
                'email' => $this->getParam('email'),
                'status' => $this->getParam('status'),
                'comment' => $this->getParam('comment'),
                'shipping' => $this->getParam('shipping'),
                'external_id' => $this->getParam('external_id'),
                'system' => $this->getParam('system', ''),
            ]);
            $this->catalogOrderProductService->proccess(
                $order,
                $this->getParam('products', [])
            );

            // notify to user-client
            if ($user_uuid && $this->parameter('notification_is_enabled', 'yes') === 'yes') {
                $this->notificationService->create([
                    'user_uuid' => $user_uuid,
                    'title' => 'Добавлен заказ: ' . $order->getSerial(),
                    'params' => [
                        'order_uuid' => $order->getUuid(),
                    ],
                ]);
            }

            switch (true) {
                case $this->getParam('save', 'exit') === 'exit':
                    return $this->respondWithRedirect('/cup/catalog/order');

                default:
                    return $this->respondWithRedirect('/cup/catalog/order/' . $order->getUuid() . '/edit');
            }
        }

        return $this->respondWithTemplate('cup/catalog/order/form.twig');
    }
}
