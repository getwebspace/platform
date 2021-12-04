<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderUpdateAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))) {
            $order = $this->catalogOrderService->read(['uuid' => $this->resolveArg('order')]);

            if ($order) {
                if ($this->isPost()) {
                    $user_uuid = $this->getParam('user_uuid');

                    // todo try/catch
                    $order = $this->catalogOrderService->update($order, [
                        'user' => $user_uuid ? $this->userService->read(['uuid' => $user_uuid]) : null,
                        'delivery' => $this->getParam('delivery'),
                        'list' => (array) $this->getParam('list', []),
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

                    switch (true) {
                        case $this->getParam('save', 'exit') === 'exit':
                            return $this->respondWithRedirect('/cup/catalog/order');

                        default:
                            return $this->respondWithRedirect('/cup/catalog/order/' . $order->getUuid() . '/edit');
                    }
                }

                return $this->respondWithTemplate('cup/catalog/order/form.twig', [
                    'order' => $order,
                ]);
            }
        }

        return $this->respondWithRedirect('/cup/catalog/order');
    }
}
