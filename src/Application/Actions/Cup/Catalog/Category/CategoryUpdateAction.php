<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;

class CategoryUpdateAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('category') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('category'))) {
            $category = $this->catalogCategoryService->read([
                'uuid' => $this->resolveArg('category'),
                'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
            ]);

            if ($category) {
                if ($this->isPost()) {
                    try {
                        $attributes = from_service_to_array(
                            $this->catalogAttributeService->read([
                                'uuid' => $this->getParam('attributes', []),
                            ])
                        );
                        $category = $this->catalogCategoryService->update($category, [
                            'parent' => $this->getParam('parent'),
                            'children' => $this->getParam('children'),
                            'title' => $this->getParam('title'),
                            'description' => $this->getParam('description'),
                            'address' => $this->getParam('address'),
                            'field1' => $this->getParam('field1'),
                            'field2' => $this->getParam('field2'),
                            'field3' => $this->getParam('field3'),
                            'attributes' => $attributes,
                            'product' => $this->getParam('product'),
                            'pagination' => $this->getParam('pagination'),
                            'order' => $this->getParam('order'),
                            'sort' => $this->getParam('sort'),
                            'meta' => $this->getParam('meta'),
                            'template' => $this->getParam('template'),
                            'external_id' => $this->getParam('external_id'),
                        ]);
                        $category = $this->processEntityFiles($category);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->respondWithRedirect('/cup/catalog/category');

                            default:
                                return $this->respondWithRedirect('/cup/catalog/category/' . $category->getUuid() . '/edit');
                        }
                    } catch (MissingTitleValueException $e) {
                        $this->addError('title', $e->getMessage());
                    } catch (AddressAlreadyExistsException $e) {
                        $this->addError('address', $e->getMessage());
                    }
                }

                $categories = $this->catalogCategoryService->read([
                    'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
                ]);
                $attributes = $this->catalogAttributeService->read();

                return $this->respondWithTemplate('cup/catalog/category/form.twig', [
                    'categories' => $categories,
                    'attributes' => $attributes,
                    'category' => $category,
                    'fields' => $this->parameter(['catalog_category_field_1', 'catalog_category_field_2', 'catalog_category_field_3']),
                    'params' => $this->parameter(['catalog_category_template', 'catalog_product_template', 'catalog_category_pagination']),
                ]);
            }
        }

        return $this->respondWithRedirect('/cup/catalog/category');
    }
}
