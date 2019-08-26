<?php

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use Exception;

class OrderCreateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
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
                    $model = new \App\Domain\Entities\Catalog\Order($data);
                    $this->entityManager->persist($model);
                    $this->entityManager->flush();

                    return $this->response->withAddedHeader('Location', '/cup/catalog/order');
                } catch (Exception $e) {
                    // todo nothing
                }
            }
        }

        return $this->respondRender('cup/catalog/order/form.twig');
    }
}
