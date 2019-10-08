<?php

namespace App\Application\Actions\Cup\Catalog\Product;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class ProductListAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $category = null;

        if (!empty($this->args['category'])) {
            if (\Ramsey\Uuid\Uuid::isValid($this->resolveArg('category'))) {
                /** @var \App\Domain\Entities\Catalog\Category $category */
                $category = $this->categoryRepository->findOneBy([
                    'uuid' => $this->resolveArg('category'),
                    'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
                ]);
            } else {
                return $this->response->withAddedHeader('Location', '/cup/shop/product')->withStatus(301);
            }
        }

        $categories = collect($this->categoryRepository->findAll());

        switch (is_null($category)) {
            case true:
                $products = collect($this->productRepository->findBy([
                    'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
                ]));
                break;
            case false:
            default:
                $products = collect($this->productRepository->findBy([
                    'category' => $this->getCategoryChildrenUUID($categories, $category),
                    'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
                ]));
                break;
        }

        return $this->respondRender('cup/catalog/product/index.twig', [
            'categories' => $categories,
            'category' => $category,
            'products' => $products
        ]);
    }

    /**
     * @param \AEngine\Entity\Collection                 $categories
     * @param \App\Domain\Entities\Catalog\Category|null $curCategory
     *
     * @return array
     */
    protected function getCategoryChildrenUUID(\AEngine\Entity\Collection $categories, \App\Domain\Entities\Catalog\Category $curCategory = null)
    {
        $result = [$curCategory->uuid->toString()];

        if ($curCategory->children) {
            /** @var \App\Domain\Entities\Catalog\Category $category */
            foreach ($categories->where('parent', $curCategory->uuid) as $childCategory) {
                $result = array_merge($result, $this->getCategoryChildrenUUID($categories, $childCategory));
            }
        }

        return $result;
    }
}
