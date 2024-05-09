<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication\Category;

use App\Application\Actions\Cup\Publication\PublicationAction;
use App\Domain\Service\Publication\Exception\CategoryNotFoundException;

class CategoryDeleteAction extends PublicationAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $category = $this->publicationCategoryService->read([
                    'uuid' => $this->resolveArg('uuid'),
                ]);

                if ($category) {
                    $uuids = $category->nested(true)->pluck('uuid')->all();

                    /**
                     * @var \App\Domain\Models\Publication $publication
                     */
                    foreach ($this->publicationService->read(['category_uuid' => $uuids]) as $publication) {
                        $this->publicationService->delete($publication);
                    }

                    /**
                     * @var \App\Domain\Models\PublicationCategory $child
                     */
                    foreach ($this->publicationCategoryService->read(['uuid' => $uuids]) as $child) {
                        $this->publicationCategoryService->delete($child);
                    }

                    $this->container->get(\App\Application\PubSub::class)->publish('cup:publication:category:delete', $category);
                }
            } catch (CategoryNotFoundException $e) {
                // nothing
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/publication/category')->withStatus(301);
    }
}
