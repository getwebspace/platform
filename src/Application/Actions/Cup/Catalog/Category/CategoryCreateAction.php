<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;

use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\TitleAlreadyExistsException;

class CategoryCreateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $parent = $this->request->getParam('parent', false);

        if ($this->request->isPost()) {
            try {
                $category = $this->catalogCategoryService->create([
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
                ]);
                $category = $this->processEntityFiles($category);

                switch (true) {
                    case $this->request->getParam('save', 'exit') === 'exit':
                        return $this->response->withRedirect('/cup/catalog/category');
                    default:
                        return $this->response->withRedirect('/cup/catalog/category/' . $category->getUuid() . '/edit');
                }
            } catch (TitleAlreadyExistsException|MissingTitleValueException $e) {
                $this->addError('title', $e->getMessage());
            } catch (AddressAlreadyExistsException $e) {
                $this->addError('address', $e->getMessage());
            }
        }

        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
        ]);

        return $this->respondWithTemplate('cup/catalog/category/form.twig', [
            'parent' => $parent,
            'categories' => $categories,
            'fields' => $this->parameter(['catalog_category_field_1', 'catalog_category_field_2', 'catalog_category_field_3']),
            'params' => $this->parameter(['catalog_category_template', 'catalog_product_template', 'catalog_category_pagination']),
        ]);
    }
}
