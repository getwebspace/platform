<?php

namespace App\Application\Actions\Cup\Publication\Category;

use App\Application\Actions\Cup\Publication\PublicationAction;

class CategoryDeleteAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \App\Domain\Entities\Publication\Category $item */
            $item = $this->categoryRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty() && $this->request->isPost()) {
                $this->entityManager->remove($item);
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/publication/category')->withStatus(301);
    }
}
