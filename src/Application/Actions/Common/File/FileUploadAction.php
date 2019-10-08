<?php

namespace App\Application\Actions\Common\File;

use Slim\Http\UploadedFile;

class FileUploadAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $path_only = $this->request->getParam('path_only', false);
        $models = [];

        foreach ($this->request->getUploadedFiles() as $field => $files) {
            if (!is_array($files)) $files = [$files];

            /* @var UploadedFile $file */
            foreach ($files as $file) {
                if (!$file->getError()) {
                    $file_model = \App\Domain\Entities\File::getFromPath($file->file, $file->getClientFilename());

                    if ($file_model) {
                        // file by user
                        if (($user = $this->request->getAttribute('user', false)) !== false) {
                            $file_model->item = \App\Domain\Types\FileItemType::ITEM_USER_UPLOAD;
                            $file_model->item_uuid = $user->uuid;
                        }

                        $this->entityManager->persist($file_model);

                        // is image
                        if (\AEngine\Support\Str::start('image/', $file_model->type)) {
                            // add task convert
                            $task = new \App\Domain\Tasks\ConvertImageTask($this->container);
                            $task->execute(['uuid' => $file_model->uuid]);

                            // run worker
                            \App\Domain\Tasks\Task::worker();
                        }

                        // save model
                        $models[$field][] = $file_model;
                    }
                }
            }
        }

        $this->entityManager->flush();

        return $this->response->withJson($path_only && !empty($field) ? ['link' => $models[$field][0]->getPublicPath()] : $models);
    }
}
