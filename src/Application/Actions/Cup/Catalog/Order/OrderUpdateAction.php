<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;
use App\Domain\Service\Catalog\Exception\WrongEmailValueException;
use App\Domain\Service\Catalog\Exception\WrongPhoneValueException;
use App\Domain\Service\User\Exception\UserNotFoundException;

class OrderUpdateAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))) {
            try {
                $order = $this->catalogOrderService->read(['uuid' => $this->resolveArg('order')]);

                if ($this->isPost()) {
                    try {
                        $order = $this->catalogOrderService->update($order, [
                            'delivery' => $this->getParam('delivery'),
                            'list' => $this->getParam('list', []),
                            'phone' => $this->getParam('phone'),
                            'email' => $this->getParam('email'),
                            'shipping' => $this->getParam('shipping'),
                            'comment' => $this->getParam('comment'),
                            'date' => $this->getParam('date', 'now'),
                            'system' => $this->getParam('system', ''),
                            'external_id' => $this->getParam('external_id'),

                            'status_uuid' => $this->getParam('status_uuid'),
                            'user_uuid' => $this->getParam('user_uuid'),
                            'payment_uuid' => $this->getParam('payment_uuid'),

                            'products' => $this->getParam('products', []),
                        ]);

                        $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:order:edit', $order);

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
                    'order' => $order,
                    'groups' => $this->userGroupService->read(),
                    'status_list' => $this->referenceService->read(['type' => ReferenceType::ORDER_STATUS, 'status' => true, 'order' => ['order' => 'asc']]),
                    'payment_list' => $this->referenceService->read(['type' => ReferenceType::PAYMENT, 'status' => true, 'order' => ['order' => 'asc']]),
                ]);
            } catch (OrderNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/catalog/order');
    }
}
