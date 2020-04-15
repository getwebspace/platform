<?php declare(strict_types=1);

namespace App\Application\Actions\Api\Catalog;

class Product extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $data = [
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
        ];

        $criteria = [];

        if ($data['uuid']) {
            $criteria['uuid'] = $this->array_criteria_uuid($data['uuid']);
        }
        if ($data['category']) {
            $criteria['category'] = $this->array_criteria_uuid($data['category']);
        }
        if ($data['address']) {
            $criteria['address'] = urldecode($data['address']);
        }
        if ($data['status']) {
            $criteria['status'] = $data['status'];
        }
        if ($data['vendorcode']) {
            $criteria['vendorcode'] = $data['vendorcode'];
        }
        if ($data['barcode']) {
            $criteria['barcode'] = $data['barcode'];
        }
        if ($data['external_id']) {
            $criteria['external_id'] = $this->array_criteria($data['external_id']);
        }

        $products = $this->productRepository->findBy($criteria, $data['order'], $data['limit'], $data['offset']);

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
