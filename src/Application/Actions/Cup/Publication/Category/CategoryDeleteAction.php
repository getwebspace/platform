<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication\Category;

use App\Application\Actions\Cup\Publication\PublicationAction;

class CategoryDeleteAction extends PublicationAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $category = $this->publicationCategoryService->read([
                'uuid' => $this->resolveArg('uuid'),
            ]);

            if ($category) {
                $childrenUuids = $category->nested(true)->reverse()->pluck('uuid')->all();

                /**
                 * @var \App\Domain\Models\Publication $publication
                 */
                foreach ($this->publicationService->read(['category_uuid' => $childrenUuids]) as $publication) {
                    $this->publicationService->delete($publication);
                }

                /**
                 * @var \App\Domain\Models\PublicationCategory $child
                 */
                foreach ($this->publicationCategoryService->read(['parent_uuid' => $childrenUuids]) as $child) {
                    $this->publicationCategoryService->delete($child);
                }

                $this->publicationCategoryService->delete($category);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:publication:category:delete', $category);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/publication/category')->withStatus(301);
    }
}
