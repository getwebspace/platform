<?php

namespace Application\Actions\Cup\Catalog\Category;

use Application\Actions\Cup\Catalog\CatalogAction;

class CategoryListAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $category = collect($this->categoryRepository->findAll());

        return $this->respondRender('cup/catalog/category/index.twig', [
            'category' => $category,
            'fields' => $this->getParameter(['catalog_category_field_1', 'catalog_category_field_2', 'catalog_category_field_3'])
        ]);
    }
}
