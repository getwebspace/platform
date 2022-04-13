<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication;

class PublicationDeleteAction extends PublicationAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $publication = $this->publicationService->read([
                'uuid' => $this->resolveArg('uuid'),
            ]);

            if ($publication) {
                $this->publicationService->delete($publication);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:publication:delete', $publication);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/publication')->withStatus(301);
    }
}
