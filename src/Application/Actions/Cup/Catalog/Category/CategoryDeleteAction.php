<?php

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class CategoryDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('category') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('category'))) {
            /** @var \App\Domain\Entities\Catalog\Category $item */
            $item = $this->categoryRepository->findOneBy(['uuid' => $this->resolveArg('category'), 'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK]);

            if (!$item->isEmpty()) {
                $categories = collect($this->categoryRepository->findBy([
                    'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
                ]));
                $childCategoriesUuid = \App\Domain\Entities\Catalog\Category::getChildren($categories, $item)->pluck('uuid')->all();

                // удаление вложенных категорий
                foreach ($this->categoryRepository->findBy(['uuid' => $childCategoriesUuid, 'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK]) as $child) {
                    $child->set('status', \App\Domain\Types\Catalog\CategoryStatusType::STATUS_DELETE);
                    $this->entityManager->persist($child);
                }

                // удаление продуктов
                foreach ($this->productRepository->findBy(['category' => $childCategoriesUuid, 'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK]) as $child) {
                    $child->set('status', \App\Domain\Types\Catalog\ProductStatusType::STATUS_DELETE);
                    $this->entityManager->persist($child);
                }

                // удаление категории
                $item->set('status', \App\Domain\Types\Catalog\CategoryStatusType::STATUS_DELETE);
                $this->entityManager->persist($item);

                // commit
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog/category')->withStatus(301);
    }
}
