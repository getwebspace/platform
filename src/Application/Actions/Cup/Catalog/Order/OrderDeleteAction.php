<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;

class OrderDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))) {
            try {
                $order = $this->catalogOrderService->read([
                    'uuid' => $this->resolveArg('order'),
                ]);

                if ($order) {
                    $this->catalogOrderService->delete($order);

                    $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:order:delete', $order);
                }
            } catch (OrderNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/catalog/order');
    }
}
