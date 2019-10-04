<?php

namespace App\Application\Actions\Common\Catalog;

use Exception;
use Slim\Http\Response;

class CartAction extends CatalogAction
{
    /**
     * @return Response
     * @throws \Doctrine\DBAL\DBALException
     * @throws \App\Domain\Exceptions\HttpBadRequestException
     */
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'delivery' => $this->request->getParam('delivery'),
                'user_uuid' => $this->request->getParam('user_uuid'),
                'list' => (array)$this->request->getParam('list', []),
                'phone' => $this->request->getParam('phone'),
                'email' => $this->request->getParam('email'),
                'comment' => $this->request->getParam('comment'),
                'shipping' => $this->request->getParam('shipping'),
                'external_id' => $this->request->getParam('external_id'),
            ];

            for ($i = 1; $i <= (int)$this->request->getParam('itemCount', 0); $i++) {
                $item = [
                    'title' => $this->request->getParam('item_name_' . $i),
                    'quantity' => $this->request->getParam('item_quantity_' . $i),
                    'price' => $this->request->getParam('item_price_' . $i),
                    'options' => $this->request->getParam('item_options_' . $i),
                ];

                foreach (explode(',', $item['options']) as $option) {
                    $option = array_map('trim', explode(':', trim($option)));
                    if ($option[0] == 'uuid' && isset($option[1]) && \Ramsey\Uuid\Uuid::isValid($option[1])) {
                        $data['list'][$option[1]] = (int)$item['quantity'];
                    }
                }
            }

            $check = \App\Domain\Filters\Catalog\Order::check($data);

            if ($check === true) {
                try {
                    $model = new \App\Domain\Entities\Catalog\Order($data);
                    $this->entityManager->persist($model);
                    $this->entityManager->flush();

                    return $this->response->withAddedHeader('Location', '/cart/done/' . $model->uuid);
                } catch (Exception $e) {
                    // todo nothing
                }
            }
        }

        return $this->respondRender($this->getParameter('catalog_cart_template', 'catalog.cart.twig'));
    }
}
