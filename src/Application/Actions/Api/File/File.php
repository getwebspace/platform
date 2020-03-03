<?php

namespace App\Application\Actions\Api\File;

use Alksily\Support\Str;

class File extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $data = [
            'uuid' => $this->request->getParam('uuid'),
            'ext' => $this->request->getParam('ext'),

            'order' => $this->request->getParam('order', []),
            'limit' => $this->request->getParam('limit', 1000),
            'offset' => $this->request->getParam('offset', 0),
        ];

        $criteria = [];

        if ($data['uuid']) {
            $criteria['uuid'] = $this->array_criteria_uuid($data['uuid']);
        }
        if ($data['ext']) {
            $criteria['ext'] = $this->array_criteria($data['ext']);
        }

        $files = $this->fileRepository->findBy($criteria, $data['order'], $data['limit'], $data['offset']);

        /** @var \App\Domain\Entities\File $file */
        foreach ($files as &$file) {
            $path = $file->getPublicPath();

            if (Str::start('image/', $file->type)) {
                $path = ['full' => $path];

                foreach (['middle', 'small'] as $size) {
                    $path[$size] = $file->getPublicPath($size);
                }
            }

            $file = $file->toArray();
            $file['path'] = $path;

            unset($file['item']);
            unset($file['item_uuid']);
        }

        return $this->respondWithData($files);
    }
}
