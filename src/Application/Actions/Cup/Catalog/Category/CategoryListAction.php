<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class CategoryListAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $category = null;

        if (!empty($this->args['parent'])) {
            if (
                $this->resolveArg('parent') !== \Ramsey\Uuid\Uuid::NIL &&
                \Ramsey\Uuid\Uuid::isValid($this->resolveArg('parent'))
            ) {
                /** @var \App\Domain\Entities\Catalog\Category $category */
                $category = $this->catalogCategoryService->read([
                    'uuid' => $this->resolveArg('parent'),
                    'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
                ]);
            } else {
                return $this->response->withRedirect('/cup/catalog/category');
            }
        }

        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
        ]);

        return $this->respondWithTemplate('cup/catalog/category/index.twig', [
            'category' => $category,
            'categories' => $categories,
            'fields' => $this->parameter(['catalog_category_field_1', 'catalog_category_field_2', 'catalog_category_field_3']),
        ]);
    }
}
