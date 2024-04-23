<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\WrongEmailValueException;
use App\Domain\Service\Catalog\Exception\WrongPhoneValueException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Casts\Reference\Type as ReferenceType;

class OrderCreateAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            $user_uuid = $this->getParam('user_uuid');
            $status_uuid = $this->getParam('status_uuid');
            $payment_uuid = $this->getParam('payment_uuid');

            try {
                $order = $this->catalogOrderService->create([
                    'user' => $user_uuid ? $this->userService->read(['uuid' => $user_uuid]) : null,
                    'delivery' => $this->getParam('delivery'),
                    'list' => $this->getParam('list', []),
                    'phone' => $this->getParam('phone'),
                    'email' => $this->getParam('email'),
                    'status' => $status_uuid ? $this->referenceService->read(['uuid' => $status_uuid, 'type' => ReferenceType::ORDER_STATUS]) : null,
                    'payment' => $payment_uuid ? $this->referenceService->read(['uuid' => $payment_uuid, 'type' => ReferenceType::PAYMENT]) : null,
                    'shipping' => $this->getParam('shipping'),
                    'comment' => $this->getParam('comment'),
                    'date' => $this->getParam('date', 'now'),
                    'system' => $this->getParam('system', ''),
                    'external_id' => $this->getParam('external_id'),

                    'products' => $this->getParam('products', []),
                ]);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:order:create', $order);

                switch (true) {
                    case $this->getParam('save', 'exit') === 'exit':
                        return $this->respondWithRedirect('/cup/catalog/order');

                    default:
                        return $this->respondWithRedirect('/cup/catalog/order/' . $order->uuid . '/edit');
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
            'status_list' => $this->referenceService->read(['type' => ReferenceType::ORDER_STATUS, 'status' => true, 'order' => ['order' => 'asc']]),
            'payment_list' => $this->referenceService->read(['type' => ReferenceType::PAYMENT, 'status' => true, 'order' => ['order' => 'asc']]),
        ]);
    }
}
