<?php declare(strict_types=1);

namespace App\Application\Actions\Api\Catalog;

use App\Domain\Service\Catalog\ProductService as CatalogProductService;

class Product extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $catalogProductService = CatalogProductService::getWithContainer($this->container);
        $products = $catalogProductService
            ->read([
                'uuid' => $this->request->getParam('uuid'),
                'category' => $this->request->getParam('category'),
                'address' => $this->request->getParam('address'),
                'status' => $this->request->getParam('status', \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK),
                'vendorcode' => $this->request->getParam('vendorcode'),
                'barcode' => $this->request->getParam('barcode'),
                'external_id' => $this->request->getParam('external_id'),

                'order' => $this->request->getParam('order', []),
                'limit' => $this->request->getParam('limit', 1000),
                'offset' => $this->request->getParam('offset', 0),
            ])
            ->toArray();

        /** @var \App\Domain\Entities\Catalog\Product $product */
        foreach ($products as &$product) {
            $files = [];

            /** @var \App\Domain\Entities\File $file */
            foreach ($product->getFiles() as $file) {
                $files[] = [
                    'full' => $file->getPublicPath('full'),
                    'middle' => $file->getPublicPath('middle'),
                    'small' => $file->getPublicPath('small'),
                ];
            }

            $product = $product->toArray();
            $product['files'] = $files;

            unset($product['buf']);
        }

        return $this->respondWithJson($products);
    }
}
