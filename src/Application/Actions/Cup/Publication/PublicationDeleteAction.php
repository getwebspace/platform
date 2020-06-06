<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication;

use App\Domain\Service\Publication\PublicationService;

class PublicationDeleteAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $publicationService = PublicationService::getWithContainer($this->container);
            $publicationService->delete($this->resolveArg('uuid'));
        }

        return $this->response->withAddedHeader('Location', '/cup/publication')->withStatus(301);
    }
}
