<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication\Category;

use App\Application\Actions\Cup\Publication\PublicationAction;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;

class CategoryListAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondWithTemplate('cup/publication/category/index.twig', ['list' => $this->publicationCategoryService->read()]);
    }
}
