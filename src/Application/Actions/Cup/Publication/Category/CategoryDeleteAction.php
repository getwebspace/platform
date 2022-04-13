<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication\Category;

use App\Application\Actions\Cup\Publication\PublicationAction;

class CategoryDeleteAction extends PublicationAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $publicationCategory = $this->publicationCategoryService->read([
                'uuid' => $this->resolveArg('uuid'),
            ]);

            if ($publicationCategory) {
                $this->publicationCategoryService->delete($publicationCategory);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:publication:category:delete', $publicationCategory);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/publication/category')->withStatus(301);
    }
}
