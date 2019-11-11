<?php

namespace App\Application\Actions\Common\Catalog;

use DateTime;
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
                if ($this->isRecaptchaChecked()) {
                    $model = new \App\Domain\Entities\Catalog\Order($data);
                    $this->entityManager->persist($model);

                    // create notify
                    $notify = new \App\Domain\Entities\Notification([
                        'title' => 'Добавлен заказ: ' . $model->serial,
                        'message' => 'Поступил новый заказ, проверьте список заказов',
                        'date' => new DateTime(),
                    ]);
                    $this->entityManager->persist($notify);

                    // send push stream
                    $this->container->get('pushstream')->send([
                        'group' => \App\Domain\Types\UserLevelType::LEVEL_ADMIN,
                        'content' => $notify,
                    ]);

                    $this->entityManager->flush();

                    // if TM is enabled
                    if ($this->getParameter('integration_trademaster_enable', 'off') === 'on') {
                        // add task send to TradeMaster
                        $task = new \App\Domain\Tasks\TradeMaster\SendOrderTask($this->container);
                        $task->execute(['uuid' => $model->uuid]);
                        $this->entityManager->flush();

                        // run worker
                        \App\Domain\Tasks\Task::worker();
                    }

                    return $this->response->withAddedHeader('Location', '/cart/done/' . $model->uuid)->withStatus(301);
                } else {
                    $this->addError('grecaptcha', \App\Domain\References\Errors\Common::WRONG_GRECAPTCHA);
                }
            } else {
                $this->addErrorFromCheck($check);
            }
        }

        return $this->respondRender($this->getParameter('catalog_cart_template', 'catalog.cart.twig'));
    }
}
