<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication\Category;

use App\Application\Actions\Cup\Publication\PublicationAction;

class CategoryListAction extends PublicationAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/publication/category/index.twig', [
            'categories' => $this->publicationCategoryService->read(['order' => ['title' => 'asc']]),
        ]);
    }
}
