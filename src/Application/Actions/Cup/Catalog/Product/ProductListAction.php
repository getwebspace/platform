<?php declare(strict_types=1);

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
            default:
                $products = collect($this->productRepository->findBy([
                    'category' => \App\Domain\Entities\Catalog\Category::getNested($categories, $category)->pluck('uuid')->all(),
                    'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
                ]));

                break;
        }

        return $this->respondWithTemplate('cup/catalog/product/index.twig', [
            'categories' => $categories,
            'category' => $category,
            'products' => $products,
        ]);
    }
}
