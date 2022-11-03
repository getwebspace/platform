<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class CategoryListAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $category = null;

        if (!empty($this->args['parent'])) {
            if (
                $this->resolveArg('parent') !== \Ramsey\Uuid\Uuid::NIL
                && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('parent'))
            ) {
                /** @var \App\Domain\Entities\Catalog\Category $category */
                $category = $this->catalogCategoryService->read([
                    'uuid' => $this->resolveArg('parent'),
                    'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
                ]);
            } else {
                return $this->respondWithRedirect('/cup/catalog/category');
            }
        }

        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
            'order' => [
                'order' => 'ASC',
                'title' => 'ASC',
            ],
        ]);

        if ($category) {
            $categories = $categories;
        }

        return $this->respondWithTemplate('cup/catalog/category/index.twig', [
            'category' => $category,
            'categories' => $categories->where('parent', $category ? $category->getUuid() : \Ramsey\Uuid\Uuid::NIL),
            'fields' => $this->parameter(['catalog_category_field_1', 'catalog_category_field_2', 'catalog_category_field_3']),
        ]);
    }
}
