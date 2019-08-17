<?php

namespace Application\Actions\Cup\Catalog\Product;

use Application\Actions\Cup\Catalog\CatalogAction;
use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class ProductListAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \Domain\Entities\Catalog\Category $category */
            $category = $this->categoryRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$category->isEmpty()) {
                /** @var \Domain\Entities\Catalog\Product $product */
                $product = collect($this->productRepository->findBy(['category' => $category->uuid]));

                return $this->respondRender('cup/catalog/product/index.twig', ['category' => $category, 'products' => $product]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog');
    }
}
