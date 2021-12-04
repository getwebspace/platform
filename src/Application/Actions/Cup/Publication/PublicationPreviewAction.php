<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication;

class PublicationPreviewAction extends PublicationAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/publication/preview.twig');
    }
}
