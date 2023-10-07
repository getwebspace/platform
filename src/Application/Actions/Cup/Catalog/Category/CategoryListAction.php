<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class CategoryListAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
            'order' => [
                'order' => 'ASC',
                'title' => 'ASC',
            ],
        ]);

        return $this->respondWithTemplate('cup/catalog/category/index.twig', [
            'categories' => $categories,
        ]);
    }
}
