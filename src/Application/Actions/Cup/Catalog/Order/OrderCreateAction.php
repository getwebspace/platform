<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderCreateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $user_uuid = $this->request->getParam('user_uuid');
            $order = $this->catalogOrderService->create([
                'user' => $user_uuid ? $this->userService->read(['uuid' => $user_uuid]) : '',
                'delivery' => $this->request->getParam('delivery'),
                'list' => $this->request->getParam('list', []),
                'phone' => $this->request->getParam('phone'),
                'email' => $this->request->getParam('email'),
                'status' => $this->request->getParam('status'),
                'comment' => $this->request->getParam('comment'),
                'shipping' => $this->request->getParam('shipping'),
                'external_id' => $this->request->getParam('external_id'),
                'system' => $this->request->getParam('system', ''),
            ]);

            // notify to user
            if ($user_uuid && $this->parameter('notification_is_enabled', 'yes') === 'yes') {
                $this->notificationService->create([
                    'user_uuid' => $user_uuid,
                    'title' => 'Добавлен заказ: ' . $order->getSerial(),
                    'message' => 'Сформирован заказ',
                    'params' => [
                        'order_uuid' => $order->getUuid(),
                    ],
                ]);
            }

            switch (true) {
                case $this->request->getParam('save', 'exit') === 'exit':
                    return $this->response->withRedirect('/cup/catalog/order');

                default:
                    return $this->response->withRedirect('/cup/catalog/order/' . $order->getUuid() . '/edit');
            }
        }

        return $this->respondWithTemplate('cup/catalog/order/form.twig');
    }
}
