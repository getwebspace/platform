<?php

namespace Application\Actions\Cup\Catalog\Category;

use AEngine\Support\Str;
use Application\Actions\Cup\Catalog\CatalogAction;
use Exception;

class CategoryCreateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
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
                    $model = new \Domain\Entities\Catalog\Category($data);
                    $this->entityManager->persist($model);
                    $this->handlerFileUpload($model);
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
            'fields' => $this->getParameter(['catalog_category_field_1', 'catalog_category_field_2', 'catalog_category_field_3']),
        ]);
    }
}
