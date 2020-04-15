<?php declare(strict_types=1);

namespace App\Application\Actions\Common\Catalog;

use DateTime;
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
                'list' => (array) $this->request->getParam('list', []),
                'phone' => $this->request->getParam('phone'),
                'email' => $this->request->getParam('email'),
                'comment' => $this->request->getParam('comment'),
                'shipping' => $this->request->getParam('shipping'),
            ];

            // пользователя заказа
            if (($user = $this->request->getAttribute('user', false)) && $user !== false) {
                $data['user_uuid'] = $user->uuid;
                $data['user'] = $user;
            }

            // другие отправленные поля дописываются в комментарий
            foreach ($this->request->getParams() as $key => $value) {
                if (!in_array($key, array_merge(array_keys($data), ['recaptcha']), true)) {
                    $data['comment'] .= $key . ' ' . $value . PHP_EOL;
                }
            }

            $check = \App\Domain\Filters\Catalog\Order::check($data);

            if ($check === true) {
                if ($this->isRecaptchaChecked()) {
                    $model = new \App\Domain\Entities\Catalog\Order($data);
                    $this->entityManager->persist($model);

                    // создаем уведомление
                    $notify = new \App\Domain\Entities\Notification([
                        'title' => 'Добавлен заказ: ' . $model->serial,
                        'message' => 'Поступил новый заказ, проверьте список заказов',
                        'date' => new DateTime(),
                    ]);
                    $this->entityManager->persist($notify);

                    // отправляем пуш
                    $this->container->get('pushstream')->send([
                        'group' => \App\Domain\Types\UserLevelType::LEVEL_ADMIN,
                        'content' => $notify,
                    ]);

                    $isNeedRunWorker = false;

                    // письмо администратору
                    if (
                        ($this->getParameter('catalog_mail_admin', 'off') === 'on') &&
                        ($email = $this->getParameter('smtp_from', '')) !== '' &&
                        ($tpl = $this->getParameter('catalog_mail_admin_template', '')) !== ''
                    ) {
                        $products = collect($this->productRepository->findBy(['uuid' => array_keys($model->list)]));

                        // add task send admin mail
                        $task = new \App\Domain\Tasks\SendMailTask($this->container);
                        $task->execute([
                            'to' => $email,
                            'body' => $this->render($tpl, ['order' => $model, 'products' => $products]),
                            'isHtml' => true,
                        ]);
                        $isNeedRunWorker = true;
                    }

                    // письмо клиенту
                    if (
                        ($this->getParameter('catalog_mail_client', 'off') === 'on') &&
                        $model->email &&
                        ($tpl = $this->getParameter('catalog_mail_client_template', '')) !== ''
                    ) {
                        $products = collect($this->productRepository->findBy(['uuid' => array_keys($model->list)]));

                        // add task send client mail
                        $task = new \App\Domain\Tasks\SendMailTask($this->container);
                        $task->execute([
                            'to' => $model->email,
                            'body' => $this->render($tpl, ['order' => $model, 'products' => $products]),
                            'isHtml' => true,
                        ]);
                        $isNeedRunWorker = true;
                    }

                    $this->entityManager->flush();

                    if ($isNeedRunWorker) {
                        // run worker
                        \App\Domain\Tasks\Task::worker();
                    }

                    if (
                        (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') && !empty($_SERVER['HTTP_REFERER'])
                    ) {
                        $this->response = $this->response->withHeader('Location', '/cart/done/' . $model->uuid)->withStatus(301);
                    }

                    return $this->respondWithJson(['redirect' => '/cart/done/' . $model->uuid]);
                }
                $this->addError('grecaptcha', \App\Domain\References\Errors\Common::WRONG_GRECAPTCHA);
            } else {
                $this->addErrorFromCheck($check);
            }
        }

        return $this->respondWithTemplate($this->getParameter('catalog_cart_template', 'catalog.cart.twig'));
    }
}
