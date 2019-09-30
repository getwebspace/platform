<?php

namespace App\Application\Actions\Common\File;

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
                    mkdir($path, 0777, true);
                }
                $item->moveTo($path . '/' . $name);

                // get file info
                $info = \App\Domain\Entities\File::info($path . '/' . $name);

                // create model
                $file_model = new \App\Domain\Entities\File([
                    'name' => $info['name'],
                    'ext'  => $info['ext'],
                    'type' => $info['type'],
                    'size' => $info['size'],
                    'hash' => $info['hash'],
                    'salt' => $salt,
                    'date' => new \DateTime(),
                ]);

                $this->entityManager->persist($file_model);

                // add task convert
                $task = new \App\Domain\Tasks\ConvertImageTask($this->container);
                $task->execute(['uuid' => $file_model->uuid]);

                // save model
                $models[$field][] = $file_model;
            }
        }

        $this->entityManager->flush();

        return $this->response->withJson($models);
    }
}
