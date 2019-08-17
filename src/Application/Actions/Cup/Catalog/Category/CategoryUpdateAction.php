<?php

namespace Application\Actions\Cup\Catalog\Category;

use Application\Actions\Cup\Catalog\CatalogAction;
use Exception;

class CategoryUpdateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \Domain\Entities\Catalog\Category $item */
            $item = $this->categoryRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty()) {
                if ($this->request->isPost()) {
                    $data = [
                        'uuid' => $item->uuid,
                        'parent' => $this->request->getParam('parent'),
                        'title' => $this->request->getParam('title'),
                        'description' => $this->request->getParam('description'),
                        'address' => $this->request->getParam('address'),
                        'field1' => $this->request->getParam('field1'),
                        'field2' => $this->request->getParam('field2'),
                        'field3' => $this->request->getParam('field3'),
                        'product' => $this->request->getParam('product'),
                        'order' => $this->request->getParam('order'),
                        'meta' => $this->request->getParam('meta'),
                        'template' => $this->request->getParam('template'),
                        'external_id' => $this->request->getParam('external_id'),
                    ];

                    $check = \Domain\Filters\Catalog\Category::check($data);

                    if ($check === true) {
                        try {
                            $item->replace($data);
                            $this->entityManager->persist($item);
                            $this->entityManager->flush();

                            return $this->response->withAddedHeader('Location', '/cup/catalog');
                        } catch (Exception $e) {
                            // todo nothing
                        }
                    }
                }

                $category = collect($this->categoryRepository->findAll());

                return $this->respondRender('cup/catalog/category/form.twig', [
                    'category' => $category,
                    'item' => $item,
                    'fields' => $this->getParameter(['catalog_category_field_1', 'catalog_category_field_2', 'catalog_category_field_3']),
                ]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog');
    }
}
