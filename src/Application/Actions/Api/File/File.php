<?php

namespace App\Application\Actions\Api\File;

class File extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $data = [
            'uuid' => $this->request->getParam('uuid'),
            'item' => $this->request->getParam('item'),
            'item_uuid' => $this->request->getParam('item_uuid'),
            'ext' => $this->request->getParam('ext'),

            'order' => $this->request->getParam('order', []),
            'limit' => $this->request->getParam('limit', 1000),
            'offset' => $this->request->getParam('offset', 0),
        ];

        $criteria = [];

        if ($data['uuid']) {
            $criteria['uuid'] = $this->array_criteria_uuid($data['uuid']);
        }
        if ($data['item']) {
            $criteria['item'] = $this->array_criteria($data['item']);
        }
        if ($data['item']) {
            $criteria['item_uuid'] = $this->array_criteria_uuid($data['item_uuid']);
        }
        if ($data['ext']) {
            $criteria['ext'] = $this->array_criteria($data['ext']);
        }

        return $this->respondWithData(
            $this->fileRepository->findBy($criteria, $data['order'], $data['limit'], $data['offset'])
        );
    }
}
