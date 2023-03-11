<?php declare(strict_types=1);

namespace App\Application\Actions\Common\Catalog;

class CartAction extends CatalogAction
{
    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \App\Domain\Exceptions\HttpBadRequestException
     */
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            $data = [
                'delivery' => $this->getParam('delivery'),
                'phone' => $this->getParam('phone'),
                'email' => $this->getParam('email'),
                'comment' => $this->getParam('comment', ''),
                'shipping' => $this->getParam('shipping'),
                'system' => $this->getParam('system', ''),

                'products' => $this->getParam('products', []),
            ];

            /**
             * Current user will be added to new order
             *
             * @var \App\Domain\Entities\User $user
             */
            $user = $this->request->getAttribute('user', false);

            if ($user) {
                $data['user'] = $user;
            }

            // add to comment other posted fields
            if ($this->parameter('catalog_order_fields', 'off') === 'on') {
                $data['comment'] = [$data['comment']];

                foreach ($this->getParams() as $key => $value) {
                    if (!in_array($key, array_merge(array_keys($data), ['recaptcha']), true) && $value) {
                        $data['comment'][] = $key . ' ' . $value;
                    }
                }

                $data['comment'] = implode(PHP_EOL, $data['comment']);
            }

            // for receive address in multiple lines
            if (is_array($data['delivery']['address'])) {
                if ($this->parameter('catalog_order_address', 'off') === 'on') {
                    ksort($data['delivery']['address']);
                }
                $data['delivery']['address'] = implode(', ', $data['delivery']['address']);
            }

            if ($this->isRecaptchaChecked()) {
                // todo try/catch
                $order = $this->catalogOrderService->create($data);

                // notify to admin and user
                if ($this->parameter('notification_is_enabled', 'yes') === 'yes') {
                    $this->notificationService->create([
                        'title' => __('Order added') . ': ' . $order->getSerial(),
                        'params' => [
                            'order_uuid' => $order->getUuid(),
                        ],
                    ]);

                    if ($user) {
                        $this->notificationService->create([
                            'user_uuid' => $user->getUuid(),
                            'title' => __('Order added') . ': ' . $order->getSerial(),
                            'params' => [
                                'order_uuid' => $order->getUuid(),
                            ],
                        ]);
                    }
                }

                $isNeedRunWorker = false;

                // mail to administrator
                if (
                    ($this->parameter('catalog_mail_admin', 'off') === 'on')
                    && ($email = $this->parameter('mail_from', '')) !== ''
                    && ($tpl = $this->parameter('catalog_mail_admin_template', '')) !== ''
                ) {
                    // add task send admin mail
                    $task = new \App\Domain\Tasks\SendMailTask($this->container);
                    $task->execute([
                        'to' => $email,
                        'template' => $tpl,
                        'data' => ['order' => $order],
                        'isHtml' => true,
                    ]);
                    $isNeedRunWorker = $task;
                }

                // mail to client
                if (
                    ($this->parameter('catalog_mail_client', 'off') === 'on')
                    && $order->getEmail()
                    && ($tpl = $this->parameter('catalog_mail_client_template', '')) !== ''
                ) {
                    // add task send client mail
                    $task = new \App\Domain\Tasks\SendMailTask($this->container);
                    $task->execute([
                        'to' => $order->getEmail(),
                        'template' => $tpl,
                        'data' => ['order' => $order],
                        'isHtml' => true,
                    ]);
                    $isNeedRunWorker = $task;
                }

                // run worker
                if ($isNeedRunWorker) {
                    \App\Domain\AbstractTask::worker($isNeedRunWorker);
                }

                $this->container->get(\App\Application\PubSub::class)->publish('common:catalog:order:create', $order);

                if (
                    (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') && !empty($_SERVER['HTTP_REFERER'])
                ) {
                    $this->response = $this->response->withHeader('Location', '/cart/done/' . $order->getUuid())->withStatus(301);
                }

                return $this->respondWithJson(['redirect' => '/cart/done/' . $order->getUuid()]);
            }

            $this->addError('grecaptcha', 'EXCEPTION_WRONG_GRECAPTCHA');
        }

        return $this->respond($this->parameter('catalog_cart_template', 'catalog.cart.twig'));
    }
}
