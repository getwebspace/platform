<?php

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class CategoryListAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $category = null;

        if (!empty($this->args['parent'])) {
            if (\Ramsey\Uuid\Uuid::isValid($this->resolveArg('parent'))) {
                /** @var \App\Domain\Entities\Catalog\Category $category */
                $category = $this->categoryRepository->findOneBy([
                    'uuid' => $this->resolveArg('parent'),
                    'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
                ]);
            } else {
                return $this->response->withAddedHeader('Location', '/cup/catalog/category');
            }
        }

        switch (is_null($category)) {
            case true:
                $categories = collect($this->categoryRepository->findBy([
                    'parent' => \Ramsey\Uuid\Uuid::NIL,
                    'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
                ]));
                break;
            case false:
            default:
                $categories = collect($this->categoryRepository->findBy([
                    'parent' => $category->uuid,
                    'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
                ]));
                break;
        }

        return $this->respondRender('cup/catalog/category/index.twig', [
            'category' => $category,
            'categories' => $categories,
            'fields' => $this->getParameter(['catalog_category_field_1', 'catalog_category_field_2', 'catalog_category_field_3'])
        ]);
    }
}
