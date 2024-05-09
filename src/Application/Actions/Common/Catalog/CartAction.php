<?php declare(strict_types=1);

namespace App\Application\Actions\Common\Catalog;

use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Plugin\AbstractPaymentPlugin;
use App\Domain\Service\Catalog\Exception\OrderShippingLimitException;
use App\Domain\Service\Catalog\Exception\WrongEmailValueException;
use App\Domain\Service\Catalog\Exception\WrongPhoneValueException;

class CartAction extends CatalogAction
{
    /**
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

            if (($user = $this->request->getAttribute('user', false)) !== false) {
                /**
                 * Current user will be added to new order
                 *
                 * @var \App\Domain\Models\User $user
                 */
                $data['user_uuid'] = $user->uuid;
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

            // find payment method
            $data['payment'] = $data['payment'] ? $this->referenceService->read(['uuid' => $data['payment'], 'type' => ReferenceType::PAYMENT]) : null;

            if ($this->isRecaptchaChecked()) {
                try {
                    // check order limit for shipping day
                    if (($limit = $this->parameter('catalog_order_limit', 0)) > 0) {
                        $date = datetime($data['shipping'])->format(\App\Domain\References\Date::DATE);

                        if ($this->catalogOrderService->getDayCount($date) >= $limit) {
                            throw new OrderShippingLimitException();
                        }
                    }

                    $order = $this->catalogOrderService->create($data);

                    $this->container->get(\App\Application\PubSub::class)->publish('common:catalog:order:create', $order);

                    // default redirect path
                    $url = '/cart/done/' . $order->uuid;

                    // if order has payment with plugin
                    if (
                        ($payment = $order->payment)
                        && ($plugin = $payment->value('plugin', false)) !== false
                    ) {
                        $plugin = $this->container
                            ->get('plugin')->get()
                            ->firstWhere(fn ($_, $name) => str_ends_with($name, $plugin));

                        if ($plugin instanceof AbstractPaymentPlugin) {
                            if (($buf = $plugin->getRedirectURL($order))) {
                                $url = $buf;
                            }
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
