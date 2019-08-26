<?php

namespace App\Application\Actions\Cup\Publication\Category;

use App\Application\Actions\Cup\Publication\PublicationAction;

class CategoryListAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->categoryRepository->findAll());

        return $this->respondRender('cup/publication/category/index.twig', ['list' => $list]);
    }
}
