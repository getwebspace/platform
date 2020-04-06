<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication;

class PublicationPreviewAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondRender('cup/publication/preview.twig');
    }
}
