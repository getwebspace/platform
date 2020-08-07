<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class CategoryDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('category') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('category'))) {
            $category = $this->catalogCategoryService->read([
                'uuid' => $this->resolveArg('category'),
                'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
            ]);

            if ($category) {
                $childrenUuids = $category->getNested($categories)->pluck('uuid')->all();

                /**
                 * @var \App\Domain\Entities\Catalog\Category $child
                 */
                foreach ($this->catalogCategoryService->read(['parent' => $childrenUuids]) as $child) {
                    $child->setStatus(\App\Domain\Types\Catalog\CategoryStatusType::STATUS_DELETE);
                    $this->catalogCategoryService->write($child);
                }

                /**
                 * @var \App\Domain\Entities\Catalog\Product $product
                 */
                foreach ($this->catalogProductService->read(['category' => $childrenUuids]) as $product) {
                    $product->setStatus(\App\Domain\Types\Catalog\ProductStatusType::STATUS_DELETE);
                    $this->catalogProductService->write($product);
                }

                $category->setStatus(\App\Domain\Types\Catalog\CategoryStatusType::STATUS_DELETE);
                $this->catalogCategoryService->write($category);
            }
        }

        return $this->response->withRedirect('/cup/catalog/category');
    }
}
