<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication;

class PublicationListAction extends PublicationAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/publication/index.twig', [
            'categories' => $this->publicationCategoryService->read(),
            'publications' => $this->publicationService->read(),
        ]);
    }
}
