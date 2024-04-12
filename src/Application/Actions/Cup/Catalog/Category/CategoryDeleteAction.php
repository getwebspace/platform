<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class CategoryDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('category') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('category'))) {
            $category = $this->catalogCategoryService->read([
                'uuid' => $this->resolveArg('category'),
                'status' => \App\Domain\Casts\Catalog\Status::WORK,
            ]);

            if ($category) {
                $childrenUuids = $category->nested(true)->reverse()->pluck('uuid')->all();

                /**
                 * @var \App\Domain\Models\CatalogCategory $child
                 */
                foreach ($this->catalogCategoryService->read(['parent_uuid' => $childrenUuids]) as $child) {
                    $this->catalogCategoryService->update($child, [
                        'status' => \App\Domain\Casts\Catalog\Status::DELETE,
                    ]);
                }

                /**
                 * @var \App\Domain\Models\CatalogProduct $product
                 */
                foreach ($this->catalogProductService->read(['category_uuid' => $childrenUuids]) as $product) {
                    $this->catalogProductService->update($product, [
                        'status' => \App\Domain\Casts\Catalog\Status::DELETE,
                    ]);
                }

                $this->catalogCategoryService->update($category, [
                    'status' => \App\Domain\Casts\Catalog\Status::DELETE,
                ]);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:category:delete', $category);
            }
        }

        return $this->respondWithRedirect('/cup/catalog/category');
    }
}
