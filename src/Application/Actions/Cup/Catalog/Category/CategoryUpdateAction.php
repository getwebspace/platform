<?php

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use Exception;

class CategoryUpdateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('category') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('category'))) {
            /** @var \App\Domain\Entities\Catalog\Category $item */
            $item = $this->categoryRepository->findOneBy(['uuid' => $this->resolveArg('category'), 'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK]);

            if (!$item->isEmpty()) {
                if ($this->request->isPost()) {
                    // remove uploaded image
                    if (($uuidFile = $this->request->getParam('delete-image')) !== null && \Ramsey\Uuid\Uuid::isValid($uuidFile)) {
                        /** @var \App\Domain\Entities\File $file */
                        $file = $this->fileRepository->findOneBy(['uuid' => $uuidFile]);

                        if ($file) {
                            try {
                                $file->unlink();
                                $this->entityManager->remove($file);
                                $this->entityManager->flush();
                            } catch (Exception $e) {
                                // todo nothing
                            }
                        }
                    } else {
                        $data = [
                            'uuid' => $item->uuid,
                            'parent' => $this->request->getParam('parent'),
                            'children' => $this->request->getParam('children'),
                            'title' => $this->request->getParam('title'),
                            'description' => $this->request->getParam('description'),
                            'address' => $this->request->getParam('address'),
                            'field1' => $this->request->getParam('field1'),
                            'field2' => $this->request->getParam('field2'),
                            'field3' => $this->request->getParam('field3'),
                            'product' => $this->request->getParam('product'),
                            'pagination' => $this->request->getParam('pagination'),
                            'order' => $this->request->getParam('order'),
                            'meta' => $this->request->getParam('meta'),
                            'template' => $this->request->getParam('template'),
                            'external_id' => $this->request->getParam('external_id'),
                        ];

                        $check = \App\Domain\Filters\Catalog\Category::check($data);

                        if ($check === true) {
                            try {
                                $item->replace($data);
                                $this->entityManager->persist($item);
                                $this->handlerFileUpload($item);
                                $this->entityManager->flush();

                                if ($this->request->getParam('save', 'exit') === 'exit') {
                                    return $this->response->withAddedHeader('Location', '/cup/catalog/category/' . $item->parent);
                                }
                            } catch (Exception $e) {
                                // todo nothing
                            }
                        }
                    }
                }

                $categories = collect($this->categoryRepository->findAll());
                $files = collect($this->fileRepository->findBy([
                    'item' => \App\Domain\Types\FileItemType::ITEM_CATALOG_CATEGORY,
                    'item_uuid' => $item->uuid,
                ]));

                return $this->respondRender('cup/catalog/category/form.twig', [
                    'categories' => $categories,
                    'files' => $files,
                    'item' => $item,
                    'fields' => $this->getParameter(['catalog_category_field_1', 'catalog_category_field_2', 'catalog_category_field_3']),
                    'params' => $this->getParameter(['catalog_category_template', 'catalog_product_template', 'catalog_category_pagination']),
                ]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog/category');
    }
}
