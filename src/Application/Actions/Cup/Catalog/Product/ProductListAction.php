<?php

namespace Application\Actions\Cup\Catalog\Product;

use Application\Actions\Cup\Catalog\CatalogAction;
use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class ProductListAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $category = null;

        if (!empty($this->args['category'])) {
            if (\Ramsey\Uuid\Uuid::isValid($this->resolveArg('category'))) {
                /** @var \Domain\Entities\Catalog\Category $category */
                $category = $this->categoryRepository->findOneBy(['uuid' => $this->resolveArg('category')]);
            } else {
                return $this->response->withAddedHeader('Location', '/cup/shop/product');
            }
        }

        $categories = collect($this->categoryRepository->findAll());

        switch (is_null($category)) {
            case true:
                $products = collect($this->productRepository->findAll());
                break;
            case false:
                $products = collect($this->productRepository->findBy(['category' => $this->getCategoryChildrenUUID($categories, $category)]));
                break;
        }

        return $this->respondRender('cup/catalog/product/index.twig', [
            'categories' => $categories,
            'category' => $category,
            'products' => $products
        ]);
    }

    /**
     * @param \AEngine\Entity\Collection             $categories
     * @param \Domain\Entities\Catalog\Category|null $curCategory
     *
     * @return array
     */
    protected function getCategoryChildrenUUID(\AEngine\Entity\Collection $categories, \Domain\Entities\Catalog\Category $curCategory = null)
    {
        $result = [$curCategory->uuid->toString()];

        if ($curCategory->children) {
            /** @var \Domain\Entities\Catalog\Category $category */
            foreach ($categories->where('parent', $curCategory->uuid) as $childCategory) {
                $result = array_merge($result, $this->getCategoryChildrenUUID($categories, $childCategory));
            }
        }

        return $result;
    }
}
