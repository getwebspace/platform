<?php declare(strict_types=1);

namespace App\Application\Actions\Common\Catalog;

use App\Domain\Plugin\AbstractPaymentPlugin;
use App\Domain\Service\Catalog\Exception\OrderShippingLimitException;
use App\Domain\Service\Catalog\Exception\WrongEmailValueException;
use App\Domain\Service\Catalog\Exception\WrongPhoneValueException;
use Doctrine\DBAL\ParameterType;

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
                'payment' => $this->getParam('payment'),
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
                try {
                    if (($limit = $this->parameter('catalog_order_limit', 0)) > 0) {
                        $date = datetime($data['shipping'], $this->parameter('common_timezone', 'UTC'))->format(\App\Domain\References\Date::DATE);

                        $qb = $this->entityManager->createQueryBuilder();
                        $count = $qb
                            ->select('count(o.serial)')
                            ->from(\App\Domain\Entities\Catalog\Order::class, 'o')
                            ->where('o.date >= :dateFrom')
                            ->andWhere('o.date <= :dateTo')
                            ->setParameter('dateFrom', $date . ' 00:00:00', ParameterType::STRING)
                            ->setParameter('dateTo', $date . ' 23:59:59', ParameterType::STRING)
                            ->getQuery()
                            ->getSingleScalarResult();

                        if ($count >= $limit) {
                            throw new OrderShippingLimitException();
                        }
                    }

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

                    $this->container->get(\App\Application\PubSub::class)->publish('common:catalog:order:create', $order);

                    // default redirect path
                    $url = '/cart/done/' . $order->getUuid();

                    // if order has plugin payment
                    if (($payment = $order->getPayment()) && ($plugin = $payment->getValue('plugin', false)) !== false) {
                        $plugin = $this->container->get('plugin')->get()->firstWhere(fn($_, $name) => str_ends_with($name, $plugin));

                        if ($plugin instanceof AbstractPaymentPlugin) {
                            $url = $plugin->getRedirectURL($order);
                        }
                    }

                    if (
                        (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') && !empty($_SERVER['HTTP_REFERER'])
                    ) {
                        $this->response = $this->response->withHeader('Location', $url)->withStatus(301);
                    }

                    return $this->respondWithJson(['redirect' => $url]);
                } catch (WrongEmailValueException $e) {
                    $this->addError('email', $e->getMessage());
                } catch (WrongPhoneValueException $e) {
                    $this->addError('phone', $e->getMessage());
                } catch (OrderShippingLimitException $e) {
                    $this->addError('shipping', $e->getMessage());
                }
            } else {
                $this->addError('grecaptcha', 'EXCEPTION_WRONG_GRECAPTCHA');
            }
        }

        return $this
            ->respond($this->parameter('catalog_cart_template', 'catalog.cart.twig'))
            ->withAddedHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
