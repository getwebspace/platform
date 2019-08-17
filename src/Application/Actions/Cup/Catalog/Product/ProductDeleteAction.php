<?php

namespace Application\Actions\Cup\Catalog\Product;

use Application\Actions\Cup\Catalog\CatalogAction;
use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class ProductDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \Domain\Entities\Page $item */
            $item = $this->pageRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty() && $this->request->isPost()) {
                $this->entityManager->remove($item);
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/page');
    }
}
