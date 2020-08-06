<?php declare(strict_types=1);

namespace App\Application\Actions\Api\Catalog;

use App\Domain\Service\Catalog\CategoryService as CatalogCatalogService;

class Category extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $catalogCategoryService = CatalogCatalogService::getWithContainer($this->container);
        $categories = $catalogCategoryService
            ->read([
                'uuid' => $this->request->getParam('uuid'),
                'parent' => $this->request->getParam('parent'),
                'address' => $this->request->getParam('address'),
                'status' => $this->request->getParam('status', \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK),
                'external_id' => $this->request->getParam('external_id'),

                'order' => $this->request->getParam('order', []),
                'limit' => $this->request->getParam('limit', 1000),
                'offset' => $this->request->getParam('offset', 0),
            ])
            ->toArray();

        /** @var \App\Domain\Entities\Catalog\Category $category */
        foreach ($categories as &$category) {
            $files = [];

            /** @var \App\Domain\Entities\File $file */
            foreach ($category->getFiles() as $file) {
                $files[] = [
                    'full' => $file->getPublicPath('full'),
                    'middle' => $file->getPublicPath('middle'),
                    'small' => $file->getPublicPath('small'),
                ];
            }

            $category = $category->toArray();
            $category['files'] = $files;

            unset($category['buf']);
        }

        return $this->respondWithJson($categories);
    }
}
