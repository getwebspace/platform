<?php

namespace Application\Actions\Cup\Catalog\Category;

use Application\Actions\Cup\Catalog\CatalogAction;
use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class CategoryListAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $category = collect($this->categoryRepository->findAll());

        return $this->respondRender('cup/catalog/category/index.twig', ['category' => $category]);
    }
}
