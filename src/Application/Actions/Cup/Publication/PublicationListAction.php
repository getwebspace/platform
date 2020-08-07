<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication;

use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;

class PublicationListAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondWithTemplate('cup/publication/index.twig', [
            'categories' => $this->publicationCategoryService->read(),
            'publications' => $this->publicationService->read(),
        ]);
    }
}
