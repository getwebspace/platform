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
                $uuids = $category->nested(true)->pluck('uuid')->all();

                /**
                 * @var \App\Domain\Models\CatalogProduct $product
                 */
                foreach ($this->catalogProductService->read(['category_uuid' => $uuids]) as $product) {
                    $this->catalogProductService->update($product, [
                        'status' => \App\Domain\Casts\Catalog\Status::DELETE,
                    ]);
                }

                /**
                 * @var \App\Domain\Models\CatalogCategory $child
                 */
                foreach ($this->catalogCategoryService->read(['uuid' => $uuids]) as $child) {
                    $this->catalogCategoryService->update($child, [
                        'status' => \App\Domain\Casts\Catalog\Status::DELETE,
                    ]);
                }

                $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:category:delete', $category);
            }
        }

        return $this->respondWithRedirect('/cup/catalog/category');
    }
}
