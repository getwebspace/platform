<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order\Status;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;

class OrderStatusCreateAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            try {
                $os = $this->catalogOrderStatusService->create([
                    'title' => $this->getParam('title'),
                    'order' => $this->getParam('order'),
                ]);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:order:status::create', $os);

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
        ]);
    }
}
