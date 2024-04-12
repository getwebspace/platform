<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\WrongTitleValueException;

class CategoryUpdateAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('category') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('category'))) {
            try {
                $category = $this->catalogCategoryService->read([
                    'uuid' => $this->resolveArg('category'),
                    'status' => \App\Domain\Casts\Catalog\Status::WORK,
                ]);

                if ($this->isPost()) {
                    try {
                        $category = $this->catalogCategoryService->update($category, [
                            'parent_uuid' => $this->getParam('parent'),
                            'children' => $this->getParam('children'),
                            'hidden' => $this->getParam('hidden'),
                            'title' => $this->getParam('title'),
                            'description' => $this->getParam('description'),
                            'address' => $this->getParam('address'),
                            'pagination' => $this->getParam('pagination'),
                            'order' => $this->getParam('order'),
                            'sort' => $this->getParam('sort'),
                            'meta' => $this->getParam('meta'),
                            'template' => $this->getParam('template'),
                            'external_id' => $this->getParam('external_id'),
                            'system' => $this->getParam('system'),

                            'attributes' => $this->getParam('attributes', []),
                        ]);
                        $category = $this->processEntityFiles($category);

                        $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:category:edit', $category);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->respondWithRedirect('/cup/catalog/category');

                            default:
                                return $this->respondWithRedirect('/cup/catalog/category/' . $category->uuid . '/edit');
                        }
                    } catch (MissingTitleValueException|WrongTitleValueException $e) {
                        $this->addError('title', $e->getMessage());
                    } catch (AddressAlreadyExistsException $e) {
                        $this->addError('address', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate('cup/catalog/category/form.twig', [
                    'categories' => $this->catalogCategoryService->read(['status' => \App\Domain\Casts\Catalog\Status::WORK]),
                    'attributes' => $this->catalogAttributeService->read(),
                    'item' => $category,
                    'params' => $this->parameter(['catalog_category_template', 'catalog_product_template', 'catalog_category_pagination']),
                ]);
            } catch (CategoryNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/catalog/category');
    }
}
