<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\WrongTitleValueException;

class CategoryCreateAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            try {
                $attributes = from_service_to_array(
                    $this->catalogAttributeService->read([
                        'uuid' => $this->getParam('attributes', []),
                    ])
                );
                $category = $this->catalogCategoryService->create([
                    'parent_uuid' => $this->getParam('parent'),
                    'children' => $this->getParam('children'),
                    'hidden' => $this->getParam('hidden'),
                    'title' => $this->getParam('title'),
                    'description' => $this->getParam('description'),
                    'address' => $this->getParam('address'),
                    'attributes' => $attributes,
                    'pagination' => $this->getParam('pagination'),
                    'order' => $this->getParam('order'),
                    'sort' => $this->getParam('sort'),
                    'meta' => $this->getParam('meta'),
                    'template' => $this->getParam('template'),
                    'external_id' => $this->getParam('external_id'),
                    'system' => $this->getParam('system'),
                ]);
                $category = $this->processEntityFiles($category);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:category:create', $category);

                switch (true) {
                    case $this->getParam('save', 'exit') === 'exit':
                        return $this->respondWithRedirect('/cup/catalog/category');

                    default:
                        return $this->respondWithRedirect('/cup/catalog/category/' . $category->getUuid() . '/edit');
                }
            } catch (MissingTitleValueException|WrongTitleValueException $e) {
                $this->addError('title', $e->getMessage());
            } catch (AddressAlreadyExistsException $e) {
                $this->addError('address', $e->getMessage());
            }
        }

        $parent = $this->getParam('parent', false);
        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
        ]);
        $attributes = $this->catalogAttributeService->read();

        return $this->respondWithTemplate('cup/catalog/category/form.twig', [
            'parent' => $parent,
            'categories' => $categories,
            'attributes' => $attributes,
            'params' => $this->parameter(['catalog_category_template', 'catalog_product_template', 'catalog_category_pagination']),
        ]);
    }
}
