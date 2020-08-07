<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication\Category;

use App\Application\Actions\Cup\Publication\PublicationAction;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;

class CategoryDeleteAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $this->publicationCategoryService->delete($this->resolveArg('uuid'));
        }

        return $this->response->withAddedHeader('Location', '/cup/publication/category')->withStatus(301);
    }
}
