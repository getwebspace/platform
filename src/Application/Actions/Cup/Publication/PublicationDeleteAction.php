<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication;

class PublicationDeleteAction extends PublicationAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $this->publicationService->delete($this->resolveArg('uuid'));
        }

        return $this->response->withAddedHeader('Location', '/cup/publication')->withStatus(301);
    }
}
