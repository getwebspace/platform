<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\WrongEmailValueException;
use App\Domain\Service\Catalog\Exception\WrongPhoneValueException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Types\ReferenceTypeType;

class OrderCreateAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            $user_uuid = $this->getParam('user_uuid');
            $status_uuid = $this->getParam('status_uuid');

            try {
                $order = $this->catalogOrderService->create([
                    'user' => $user_uuid ? $this->userService->read(['uuid' => $user_uuid]) : null,
                    'delivery' => $this->getParam('delivery'),
                    'list' => $this->getParam('list', []),
                    'phone' => $this->getParam('phone'),
                    'email' => $this->getParam('email'),
                    'status' => $status_uuid ? $this->referenceService->read(['uuid' => $status_uuid, 'type' => ReferenceTypeType::TYPE_ORDER_STATUS]) : null,
                    'comment' => $this->getParam('comment'),
                    'shipping' => $this->getParam('shipping'),
                    'external_id' => $this->getParam('external_id'),
                    'system' => $this->getParam('system', ''),

                    'products' => $this->getParam('products', []),
                ]);

                // notify to user-client
                if ($user_uuid && $this->parameter('notification_is_enabled', 'yes') === 'yes') {
                    $this->notificationService->create([
                        'user_uuid' => $user_uuid,
                        'title' => __('Order added') . ': ' . $order->getSerial(),
                        'params' => [
                            'order_uuid' => $order->getUuid(),
                        ],
                    ]);
                }

                $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:order:create', $order);

                switch (true) {
                    case $this->getParam('save', 'exit') === 'exit':
                        return $this->respondWithRedirect('/cup/catalog/order');

                    default:
                        return $this->respondWithRedirect('/cup/catalog/order/' . $order->getUuid() . '/edit');
                }
            } catch (WrongEmailValueException $e) {
                $this->addError('email', $e->getMessage());
            } catch (WrongPhoneValueException $e) {
                $this->addError('phone', $e->getMessage());
            } catch (UserNotFoundException $e) {
                $this->addError('user_uuid', $e->getMessage());
            }
        }

        return $this->respondWithTemplate('cup/catalog/order/form.twig', [
            'groups' => $this->userGroupService->read(),
            'status_list' => $this->referenceService->read(['type' => ReferenceTypeType::TYPE_ORDER_STATUS]),
        ]);
    }
}
