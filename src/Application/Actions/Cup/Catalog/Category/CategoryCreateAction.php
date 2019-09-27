<?php

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use Exception;

class CategoryCreateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $parent = $this->request->getParam('parent', false);

        if ($this->request->isPost()) {
            $data = [
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
                    $model = new \App\Domain\Entities\Catalog\Category($data);
                    $this->entityManager->persist($model);
                    $this->handlerFileUpload($model);
                    $this->entityManager->flush();

                    switch (true) {
                        case $this->request->getParam('save', 'exit') === 'exit':
                            return $this->response->withAddedHeader('Location', '/cup/catalog/category/' . $model->parent);
                        default:
                            return $this->response->withAddedHeader('Location', '/cup/catalog/category/' . $model->uuid . '/edit');
                    }
                } catch (Exception $e) {
                    // todo nothing
                }
            }
        }

        $categories = collect($this->categoryRepository->findAll());

        return $this->respondRender('cup/catalog/category/form.twig', [
            'parent' => $parent,
            'categories' => $categories,
            'fields' => $this->getParameter(['catalog_category_field_1', 'catalog_category_field_2', 'catalog_category_field_3']),
            'params' => $this->getParameter(['catalog_category_template', 'catalog_product_template', 'catalog_category_pagination']),
        ]);
    }
}
