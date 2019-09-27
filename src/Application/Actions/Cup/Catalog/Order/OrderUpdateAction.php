<?php

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use Exception;

class OrderUpdateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))) {
            /** @var \App\Domain\Entities\Catalog\Order $order */
            $order = $this->orderRepository->findOneBy(['uuid' => $this->resolveArg('order')]);

            if (!$order->isEmpty()) {
                $products = collect($this->productRepository->findBy(['uuid' => array_keys($order->list)]));

                if ($this->request->isPost()) {
                    $data = [
                        'uuid' => $order->uuid,
                        'serial' => $order->serial,
                        'delivery' => $this->request->getParam('delivery'),
                        'user_uuid' => $this->request->getParam('user_uuid'),
                        'list' => (array)$this->request->getParam('list', []),
                        'phone' => $this->request->getParam('phone'),
                        'email' => $this->request->getParam('email'),
                        'status' => $this->request->getParam('status'),
                        'comment' => $this->request->getParam('comment'),
                        'shipping' => $this->request->getParam('shipping'),
                        'external_id' => $this->request->getParam('external_id'),
                    ];

                    $check = \App\Domain\Filters\Catalog\Order::check($data);

                    if ($check === true) {
                        try {
                            $order->replace($data);
                            $this->entityManager->persist($order);
                            $this->entityManager->flush();

                            if ($this->request->getParam('save', 'exit') === 'exit') {
                                return $this->response->withAddedHeader('Location', '/cup/catalog/order');
                            }
                        } catch (Exception $e) {
                            // todo nothing
                        }
                    }
                }

                return $this->respondRender('cup/catalog/order/form.twig', [
                    'order' => $order,
                    'products' => $products,
                ]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog/order');
    }
}