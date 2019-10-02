<?php

namespace App\Application\Actions\Api\Catalog;

class Category extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $data = [
            'uuid' => $this->request->getParam('uuid'),
            'parent' => $this->request->getParam('parent'),
            'address' => $this->request->getParam('address'),
            'status' => $this->request->getParam('status', \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK),
            'external_id' => $this->request->getParam('external_id'),

            'order' => $this->request->getParam('order', []),
            'limit' => $this->request->getParam('limit', 1000),
            'offset' => $this->request->getParam('offset', 0),
        ];

        $criteria = [];

        if ($data['uuid']) {
            $criteria['uuid'] = $this->array_criteria_uuid($data['uuid']);
        }
        if ($data['parent']) {
            $criteria['parent'] = $this->array_criteria_uuid($data['parent']);
        }
        if ($data['address']) {
            $criteria['address'] = $data['address'];
        }
        if ($data['status']) {
            $criteria['status'] = $data['status'];
        }
        if ($data['external_id']) {
            $criteria['external_id'] = $this->array_criteria($data['external_id']);
        }

        return $this->respondWithData(
            $this->categoryRepository->findBy($criteria, $data['order'], $data['limit'], $data['offset'])
        );
    }
}
