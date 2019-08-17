<?php

namespace Application\Actions\Common;

use AEngine\Support\Str;
use Slim\Http\UploadedFile;

class FileUploadAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $models = [];

        foreach ($this->request->getUploadedFiles() as $field => $files) {
            if (!is_array($files)) $files = [$files];

            /* @var UploadedFile $item */
            foreach ($files as $item) {
                $salt = uniqid();
                $name = Str::translate(strtolower($item->getClientFilename()));
                $path = UPLOAD_DIR . '/' . $salt;

                if (!file_exists($path)) {
                    mkdir($path);
                }

                // create model
                $model = new \Domain\Entities\File([
                    'name' => $name,
                    'type' => $item->getClientMediaType(),
                    'size' => (int)$item->getSize(),
                    'salt' => $salt,
                    'date' => new \DateTime(),
                ]);

                $item->moveTo($path . '/' . $name);
                $model->set('hash', sha1_file($path . '/' . $name));

                $this->entityManager->persist($model);

                // save model
                $models[$field][] = $model;
            }
        }

        $this->entityManager->flush();

        return $this->response->withJson($models);
    }
}
