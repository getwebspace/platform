<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderCreateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $order = $this->catalogOrderService->create([
                'delivery' => $this->request->getParam('delivery'),
                'user_uuid' => $this->request->getParam('user_uuid'),
                'list' => $this->request->getParam('list', []),
                'phone' => $this->request->getParam('phone'),
                'email' => $this->request->getParam('email'),
                'status' => $this->request->getParam('status'),
                'comment' => $this->request->getParam('comment'),
                'shipping' => $this->request->getParam('shipping'),
                'external_id' => $this->request->getParam('external_id'),
            ]);

            // notify to user
            if ($order->getUserUuid() !== \Ramsey\Uuid\Uuid::NIL) {
                $this->notificationService->create([
                    'user_uuid' => $order->getUserUuid(),
                    'title' => 'Ваш заказ: ' . $order->getSerial(),
                    'message' => 'Для вас был добавлен заказ',
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
