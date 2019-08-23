<?php

namespace Application\Actions\Cup\Catalog\Order;

use AEngine\Support\Str;
use Application\Actions\Cup\Catalog\CatalogAction;
use Exception;

class OrderUpdateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))) {
            /** @var \Domain\Entities\Catalog\Order $order */
            $order = $this->orderRepository->findOneBy(['uuid' => $this->resolveArg('order')]);

            if (!$order->isEmpty()) {
                if ($this->request->isPost()) {
                    $data = [
                        'uuid' => $order->uuid,
                        'delivery' => $this->request->getParam('delivery'),
                        'user_uuid' => $this->request->getParam('user_uuid'),
                        'items' => (array)$this->request->getParam('items', []),
                        'status' => $this->request->getParam('status'),
                        'comment' => $this->request->getParam('comment'),
                        'shipping' => $this->request->getParam('shipping'),
                        'external_id' => $this->request->getParam('external_id'),
                    ];

                    $check = \Domain\Filters\Catalog\Order::check($data);

                    if ($check === true) {
                        exit;


                        try {
                            $product->replace($data);
                            $this->entityManager->persist($product);
                            $this->handlerFileUpload($product);
                            $this->entityManager->flush();

                            return $this->response->withAddedHeader('Location', '/cup/catalog/' . $category->uuid . '/product');
                        } catch (Exception $e) {
                            // todo nothing
                        }
                    }
                }

                return $this->respondRender('cup/catalog/order/form.twig', [
                    'item' => $order
                ]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog/order');
    }
}
