<?php

namespace Application\Actions\Cup\Catalog\Category;

use Application\Actions\Cup\Catalog\CatalogAction;

class CategoryDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('category') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('category'))) {
            /** @var \Domain\Entities\Catalog\Category $item */
            $item = $this->categoryRepository->findOneBy(['uuid' => $this->resolveArg('category')]);

            if (!$item->isEmpty() && $this->request->isPost()) {
                $categories = collect($this->categoryRepository->findAll());
                $childCategoriesUuid = $this->getCategoryChildrenUUID($categories, $item);

                // remove children category
                foreach ($this->categoryRepository->findBy(['uuid' => $childCategoriesUuid]) as $child) {
                    $this->entityManager->remove($child);
                }

                // remove children category
                foreach ($this->productRepository->findBy(['category' => $childCategoriesUuid]) as $child) {
                    $this->entityManager->remove($child);
                }

                // remove category
                $this->entityManager->remove($item);

                // commit
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog/category');
    }

    /**
     * @param \AEngine\Entity\Collection             $categories
     * @param \Domain\Entities\Catalog\Category|null $curCategory
     *
     * @return array
     */
    protected function getCategoryChildrenUUID(\AEngine\Entity\Collection $categories, \Domain\Entities\Catalog\Category $curCategory = null)
    {
        $result = [];

        /** @var \Domain\Entities\Catalog\Category $category */
        foreach ($categories->where('parent', $curCategory->uuid) as $childCategory) {
            $result[] = $childCategory->uuid->toString();
            $result = array_merge($result, $this->getCategoryChildrenUUID($categories, $childCategory));
        }

        return $result;
    }
}
