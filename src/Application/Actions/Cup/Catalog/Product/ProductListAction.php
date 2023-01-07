<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Product;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class ProductListAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
        ]);
        $products = $this->catalogProductService->read([
            'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
            'order' => [
                'category' => 'ASC',
                'order' => 'ASC',
            ],
        ]);

        return $this->respondWithTemplate('cup/catalog/product/index.twig', [
            'categories' => $categories,
            'products' => $products,
        ]);
    }
}
