<?php

namespace App\Application\Actions\Cup\Catalog\Product;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class ProductDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $item = null;

        if ($this->resolveArg('product') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('product'))) {
            /** @var \App\Domain\Entities\Catalog\Product $item */
            $item = $this->productRepository->findOneBy(['uuid' => $this->resolveArg('product'), 'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK]);

            if (!$item->isEmpty() && $this->request->isPost()) {
                $item->set('status', \App\Domain\Types\Catalog\ProductStatusType::STATUS_DELETE);
                $this->entityManager->persist($item);
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog/product' . ($item ? '/' . $item->category : ''));
    }
}
