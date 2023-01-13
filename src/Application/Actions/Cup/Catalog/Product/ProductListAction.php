<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Product;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class ProductListAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $category = null;

        if (!empty($this->args['category'])) {
            if (
                $this->resolveArg('category') !== \Ramsey\Uuid\Uuid::NIL
                && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('category'))
            ) {
                /** @var \App\Domain\Entities\Catalog\Category $category */
                $category = $this->catalogCategoryService->read([
                    'uuid' => $this->resolveArg('category'),
                    'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
                ]);
            } else {
                return $this->respondWithRedirect('/cup/catalog/product');
            }
        }

        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
        ]);
        $products = $this->catalogProductService->read([
            'category' => $category ? $category->getNested($categories)->pluck('uuid')->all() : null,
            'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
            'order' => [
                'category' => 'ASC',
                'order' => 'ASC',
                'title' => 'ASC',
            ],
        ]);

        return $this->respondWithTemplate('cup/catalog/product/index.twig', [
            'categories' => $categories,
            'category' => $category,
            'products' => $products,
        ]);
    }
}
