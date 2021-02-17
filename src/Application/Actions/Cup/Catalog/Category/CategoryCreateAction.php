<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;

class CategoryCreateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            try {
                $attributes = from_service_to_array(
                    $this->catalogAttributeService->read([
                        'uuid' => $this->request->getParam('attributes', []),
                    ])
                );
                $category = $this->catalogCategoryService->create([
                    'parent' => $this->request->getParam('parent'),
                    'children' => $this->request->getParam('children'),
                    'title' => $this->request->getParam('title'),
                    'description' => $this->request->getParam('description'),
                    'address' => $this->request->getParam('address'),
                    'field1' => $this->request->getParam('field1'),
                    'field2' => $this->request->getParam('field2'),
                    'field3' => $this->request->getParam('field3'),
                    'attributes' => $attributes,
                    'product' => $this->request->getParam('product'),
                    'pagination' => $this->request->getParam('pagination'),
                    'order' => $this->request->getParam('order'),
                    'meta' => $this->request->getParam('meta'),
                    'template' => $this->request->getParam('template'),
                    'external_id' => $this->request->getParam('external_id'),
                ]);
                $category = $this->processEntityFiles($category);

                switch (true) {
                    case $this->request->getParam('save', 'exit') === 'exit':
                        return $this->response->withRedirect('/cup/catalog/category');
                    default:
                        return $this->response->withRedirect('/cup/catalog/category/' . $category->getUuid() . '/edit');
                }
            } catch (MissingTitleValueException $e) {
                $this->addError('title', $e->getMessage());
            } catch (AddressAlreadyExistsException $e) {
                $this->addError('address', $e->getMessage());
            }
        }

        $parent = $this->request->getParam('parent', false);
        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
        ]);
        $attributes = $this->catalogAttributeService->read();

        return $this->respondWithTemplate('cup/catalog/category/form.twig', [
            'parent' => $parent,
            'categories' => $categories,
            'attributes' => $attributes,
            'fields' => $this->parameter(['catalog_category_field_1', 'catalog_category_field_2', 'catalog_category_field_3']),
            'params' => $this->parameter(['catalog_category_template', 'catalog_product_template', 'catalog_category_pagination']),
        ]);
    }
}
