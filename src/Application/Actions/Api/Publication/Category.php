<?php

namespace App\Application\Actions\Api\Publication;

class Category extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        $data = [
            'uuid' => $this->request->getParam('uuid'),
            'parent' => $this->request->getParam('parent'),
            'address' => $this->request->getParam('address'),

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

        return $this->respondWithData(
            $this->categoryRepository->findBy($criteria, $data['order'], $data['limit'], $data['offset'])
        );
    }
}
