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

            /* @var UploadedFile $file */
            foreach ($files as $file) {
                if (!$file->getError()) {
                    $file_model = \App\Domain\Entities\File::getFromPath($file->file, $file->getClientFilename());

                    if ($file_model) {
                        $this->entityManager->persist($file_model);

                        // add task convert
                        $task = new \App\Domain\Tasks\ConvertImageTask($this->container);
                        $task->execute(['uuid' => $file_model->uuid]);

                        // save model
                        $models[$field][] = $file_model;
                    }
                }
            }
        }

        $this->entityManager->flush();

        return $this->response->withJson($models);
    }
}
