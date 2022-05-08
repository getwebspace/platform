<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order\Status;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\OrderStatusNotFoundException;

class OrderStatusUpdateAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $os = $this->catalogOrderStatusService->read([
                    'uuid' => $this->resolveArg('uuid'),
                ]);

                if ($this->isPost()) {
                    try {
                        $os = $this->catalogOrderStatusService->update($os, [
                            'title' => $this->getParam('title'),
                            'order' => $this->getParam('order'),
                        ]);

                        $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:order:status:edit', $os);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->respondWithRedirect('/cup/catalog/order');

                            default:
                                return $this->respondWithRedirect('/cup/catalog/order/status/' . $os->getUuid() . '/edit');
                        }
                    } catch (MissingTitleValueException $e) {
                        $this->addError('title', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate('cup/catalog/order/status/form.twig', [
                    'list' => $this->catalogOrderStatusService->read(),
                    'item' => $os,
                ]);
            } catch (OrderStatusNotFoundException $e) {
                return $this->respondWithRedirect('/cup/catalog/order');
            }
        }

        return $this->respondWithRedirect('/cup/catalog/order');
    }
}
