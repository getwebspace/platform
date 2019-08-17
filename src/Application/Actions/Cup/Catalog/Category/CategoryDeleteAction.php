<?php

namespace Application\Actions\Cup\Catalog\Category;

use Application\Actions\Cup\Catalog\CatalogAction;
use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class CategoryDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \Domain\Entities\Catalog\Category $item */
            $item = $this->categoryRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty() && $this->request->isPost()) {
                $this->entityManager->remove($item);
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog');
    }
}
