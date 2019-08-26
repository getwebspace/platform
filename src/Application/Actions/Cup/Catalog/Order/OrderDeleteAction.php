<?php

namespace Application\Actions\Cup\Catalog\Order;

use Application\Actions\Cup\Catalog\CatalogAction;
use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class OrderDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))) {
            /** @var \Domain\Entities\Catalog\Order $item */
            $item = $this->orderRepository->findOneBy(['uuid' => $this->resolveArg('order')]);

            if (!$item->isEmpty() && $this->request->isPost()) {
                $this->entityManager->remove($item);
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog/order');
    }
}
