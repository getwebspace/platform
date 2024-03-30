<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Product;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class ProductListAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $category = null;

        if (!empty($this->args['category'])) {
            if (\Ramsey\Uuid\Uuid::isValid($this->resolveArg('category'))) {
                /** @var \App\Domain\Models\CatalogCategory $category */
                $category = $this->catalogCategoryService->read([
                    'uuid' => $this->resolveArg('category'),
                    'status' => \App\Domain\Casts\Catalog\Status::WORK,
                ]);
            } else {
                return $this->respondWithRedirect('/cup/catalog/product');
            }
        }

        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Casts\Catalog\Status::WORK,
        ]);
        $products = $this->catalogProductService->read([
            'category_uuid' => $category?->uuid,
            'status' => \App\Domain\Casts\Catalog\Status::WORK,
            'order' => [
                'category' => 'ASC',
                'order' => 'ASC',
                'title' => 'ASC',
            ],
        ]);

        return $this->respondWithTemplate('cup/catalog/product/index.twig', [
            'categories' => $categories,
            'products' => $products,
        ]);
    }
}
