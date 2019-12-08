<?php

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
            $criteria['address'] = $data['address'];
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
            $criteria['category'] = $this->array_criteria($data['external_id']);
        }

        return $this->respondWithData(
            $this->productRepository->findBy($criteria, $data['order'], $data['limit'], $data['offset'])
        );
    }
}
