<?php

namespace Application\Actions\Cup\Catalog\Product;

use Application\Actions\Cup\Catalog\CatalogAction;
use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class ProductDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $item = null;

        if ($this->resolveArg('product') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('product'))) {
            /** @var \Domain\Entities\Catalog\Product $item */
            $item = $this->productRepository->findOneBy(['uuid' => $this->resolveArg('product')]);

            if (!$item->isEmpty() && $this->request->isPost()) {
                $this->entityManager->remove($item);
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog/product' . ($item ? '/' . $item->category : ''));
    }
}
