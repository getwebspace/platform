<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\CategoryService as CatalogCatalogService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;

class CategoryDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('category') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('category'))) {
            $catalogCategoryService = CatalogCatalogService::getWithContainer($this->container);
            $catalogProductService = CatalogProductService::getWithContainer($this->container);

            $category = $catalogCategoryService->read([
                'uuid' => $this->resolveArg('category'),
                'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
            ]);

            if ($category) {
                $childrenUuids = $category->getNested($categories)->pluck('uuid')->all();

                /**
                 * @var \App\Domain\Entities\Catalog\Category $child
                 */
                foreach ($catalogCategoryService->read(['parent' => $childrenUuids]) as $child) {
                    $child->setStatus(\App\Domain\Types\Catalog\CategoryStatusType::STATUS_DELETE);
                    $catalogCategoryService->write($child);
                }

                /**
                 * @var \App\Domain\Entities\Catalog\Product $product
                 */
                foreach ($catalogProductService->read(['category' => $childrenUuids]) as $product) {
                    $product->setStatus(\App\Domain\Types\Catalog\ProductStatusType::STATUS_DELETE);
                    $catalogProductService->write($product);
                }

                $category->setStatus(\App\Domain\Types\Catalog\CategoryStatusType::STATUS_DELETE);
                $catalogCategoryService->write($category);
            }
        }

        return $this->response->withRedirect('/cup/catalog/category');
    }
}
