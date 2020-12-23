<?php declare(strict_types=1);

namespace App\Application\Actions\Common\Catalog;

use Slim\Http\Response;

class CartAction extends CatalogAction
{
    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \App\Domain\Exceptions\HttpBadRequestException
     *
     * @return Response
     */
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'delivery' => $this->request->getParam('delivery'),
                'list' => $this->request->getParam('list', []),
                'phone' => $this->request->getParam('phone'),
                'email' => $this->request->getParam('email'),
                'comment' => $this->request->getParam('comment'),
                'shipping' => $this->request->getParam('shipping'),
                'system' => $this->request->getParam('system'),
            ];

            /**
             * order user
             * @var \App\Domain\Entities\User $user
             */
            $user = $this->request->getAttribute('user', false);

            if ($user) {
                $data['user_uuid'] = $user->getUuid();
            }

            // add to comment other posted fields
            if ($this->parameter('catalog_order_fields', 'off') === 'on') {
                $data['comment'] = [$data['comment']];

                foreach ($this->request->getParams() as $key => $value) {
                    if (!in_array($key, array_merge(array_keys($data), ['recaptcha']), true) && $value) {
                        $data['comment'] .= '; ' . $key . ' ' . $value . PHP_EOL;
                    }
                }

                $data['comment'] = implode(PHP_EOL, $data['comment']);
            }

            if ($this->isRecaptchaChecked()) {
                $order = $this->catalogOrderService->create($data);

                // notify to admin
                $this->notificationService->create([
                    'title' => 'Добавлен заказ: ' . $order->getSerial(),
                    'message' => 'Поступил новый заказ, проверьте список заказов',
                    'params' => [
                        'order_uuid' => $order->getUuid(),
                    ],
                ]);

                // notify to user
                if ($user && $user->getLevel() === \App\Domain\Types\UserLevelType::LEVEL_USER) {
                    $this->notificationService->create([
                        'user_uuid' => $user->getUuid(),
                        'title' => 'Ваш заказ: ' . $order->getSerial(),
                        'message' => 'Кто-то, возможно вы, добавил заказ от вашего имени',
                        'params' => [
                            'order_uuid' => $order->getUuid(),
                        ],
                    ]);
                }

                $isNeedRunWorker = false;

                // mail to administrator
                if (
                    ($this->parameter('catalog_mail_admin', 'off') === 'on') &&
                    ($email = $this->parameter('smtp_from', '')) !== '' &&
                    ($tpl = $this->parameter('catalog_mail_admin_template', '')) !== ''
                ) {
                    $products = $this->catalogProductService->read(['uuid' => array_keys($order->getList())]);

                    // add task send admin mail
                    $task = new \App\Domain\Tasks\SendMailTask($this->container);
                    $task->execute([
                        'to' => $email,
                        'body' => $this->render($tpl, ['order' => $order, 'products' => $products]),
                        'isHtml' => true,
                    ]);
                    $isNeedRunWorker = true;
                }

                // mail to client
                if (
                    ($this->parameter('catalog_mail_client', 'off') === 'on') &&
                    $order->getEmail() &&
                    ($tpl = $this->parameter('catalog_mail_client_template', '')) !== ''
                ) {
                    $products = $this->catalogProductService->read(['uuid' => array_keys($order->getList())]);

                    // add task send client mail
                    $task = new \App\Domain\Tasks\SendMailTask($this->container);
                    $task->execute([
                        'to' => $order->getEmail(),
                        'body' => $this->render($tpl, ['order' => $order, 'products' => $products]),
                        'isHtml' => true,
                    ]);
                    $isNeedRunWorker = true;
                }

                // run worker
                if ($isNeedRunWorker) {
                    \App\Domain\AbstractTask::worker();
                }

                if (
                    (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') && !empty($_SERVER['HTTP_REFERER'])
                ) {
                    $this->response = $this->response->withHeader('Location', '/cart/done/' . $order->getUuid())->withStatus(301);
                }

                return $this->respondWithJson(['redirect' => '/cart/done/' . $order->getUuid()]);
            }

            $this->addError('grecaptcha', \App\Domain\References\Errors\Common::WRONG_GRECAPTCHA);
        }

        return $this->respondWithTemplate($this->parameter('catalog_cart_template', 'catalog.cart.twig'));
    }
}
