<?php

namespace Application\Actions\Cup\Publication;

use Exception;

class PublicationPreviewAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondRender('cup/publication/preview.twig');
    }
}
