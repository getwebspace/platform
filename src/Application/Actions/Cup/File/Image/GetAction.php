<?php

namespace App\Application\Actions\Cup\File\Image;

use Alksily\Support\Str;
use App\Application\Actions\Cup\File\FileAction;

class GetAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $data = [
            'item' => $this->request->getParam('item', false),
            'item_uuid' => $this->request->getParam('item_uuid', false),
        ];

        $collection = collect($this->fileRepository->findBy([], [], 1000));

        if ($data['item'] !== false) {
            $collection = $collection->sortBy(function ($file) use ($data) {
                switch (true) {
                    case $file->item == $data['item'] && $file->item_uuid == $data['item_uuid']: return 1;
                    case $file->item == $data['item']: return 2;
                    default: return 3;
                }
            });
        }

        $result = [];

        /** @var \App\Domain\Entities\File $file */
        foreach ($collection as $file) {
            if (Str::start('image/', $file->type)) {
                $result[] = [
                    'url' => $file->getPublicPath(),
                    'thumb' => $file->getPublicPath('small'),
                ];
            }
        }

        return $this->respondWithJson($result);
    }
}
