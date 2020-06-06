<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication;

use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;

class PublicationListAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        $publicationCategoryService = PublicationCategoryService::getWithContainer($this->container);
        $publicationService = PublicationService::getWithContainer($this->container);

        return $this->respondWithTemplate('cup/publication/index.twig', [
            'categories' => $publicationCategoryService->read(),
            'publications' => $publicationService->read(),
        ]);
    }
}
