<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderDispatchAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))) {
            try {
                return $this->respondWithTemplate('cup/catalog/order/dispatch.twig', [
                    'order' => $this->catalogOrderService->read(['uuid' => $this->resolveArg('order')]),
                    'template' => $this->parameter('catalog_dispatch', ''),
                ]);
            } catch (\App\Domain\Service\Catalog\Exception\OrderNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/catalog/order');
    }
}
