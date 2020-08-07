<?php declare(strict_types=1);

namespace App\Application\Actions\Api\File;

use Alksily\Support\Str;

class File extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $files = from_service_to_array($this->fileService->read([
            'uuid' => $this->request->getParam('uuid'),
            'name' => $this->request->getParam('name'),
            'ext' => $this->request->getParam('ext'),

            'order' => $this->request->getParam('order', []),
            'limit' => $this->request->getParam('limit', 1000),
            'offset' => $this->request->getParam('offset', 0),
        ]));

        /** @var \App\Domain\Entities\File $file */
        foreach ($files as &$file) {
            $path = $file->getPublicPath();

            if (Str::start('image/', $file->getType())) {
                $path = ['full' => $path];

                foreach (['middle', 'small'] as $size) {
                    $path[$size] = $file->getPublicPath($size);
                }
            }

            $file = $file->toArray();
            $file['path'] = $path;
        }

        return $this->respondWithJson($files);
    }
}
