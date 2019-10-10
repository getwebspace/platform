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

            if (!$item->isEmpty() && $this->request->isPost()) {
                $categories = collect($this->categoryRepository->findBy([
                    'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
                ]));
                $childCategoriesUuid = $this->getCategoryChildrenUUID($categories, $item);

                // remove children category
                foreach ($this->categoryRepository->findBy(['uuid' => $childCategoriesUuid, 'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK]) as $child) {
                    $child->set('status', \App\Domain\Types\Catalog\CategoryStatusType::STATUS_DELETE);
                    $this->entityManager->persist($child);
                }

                // remove children category
                foreach ($this->productRepository->findBy(['category' => $childCategoriesUuid, 'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK]) as $child) {
                    $child->set('status', \App\Domain\Types\Catalog\ProductStatusType::STATUS_DELETE);
                    $this->entityManager->persist($child);
                }

                // remove category
                $item->set('status', \App\Domain\Types\Catalog\CategoryStatusType::STATUS_DELETE);
                $this->entityManager->persist($item);

                // commit
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog/category')->withStatus(301);
    }

    /**
     * @param \Alksily\Entity\Collection                 $categories
     * @param \App\Domain\Entities\Catalog\Category|null $curCategory
     *
     * @return array
     */
    protected function getCategoryChildrenUUID(\Alksily\Entity\Collection $categories, \App\Domain\Entities\Catalog\Category $curCategory = null)
    {
        $result = [$curCategory->uuid->toString()];

        /** @var \App\Domain\Entities\Catalog\Category $category */
        foreach ($categories->where('parent', $curCategory->uuid) as $childCategory) {
            $result = array_merge($result, $this->getCategoryChildrenUUID($categories, $childCategory));
        }

        return $result;
    }
}
