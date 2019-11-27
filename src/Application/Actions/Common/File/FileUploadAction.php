<?php

namespace App\Application\Actions\Common\File;

use Slim\Http\UploadedFile;

class FileUploadAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $path_only = $this->request->getParam('path_only', false);
        $item = $this->request->getParam('item', false);
        $item_uuid = $this->request->getParam('item_uuid', false);

        $models = [];

        foreach ($this->request->getUploadedFiles() as $field => $files) {
            if (!is_array($files)) $files = [$files];

            /* @var UploadedFile $file */
            foreach ($files as $file) {
                if (!$file->getError()) {
                    $file_model = \App\Domain\Entities\File::getFromPath($file->file, $file->getClientFilename());

                    if ($file_model) {
                        // файл загружен пользователем
                        if (($user = $this->request->getAttribute('user', false)) !== false) {
                            $file_model->item = \App\Domain\Types\FileItemType::ITEM_USER_UPLOAD;
                            $file_model->item_uuid = $user->uuid;
                        }

                        // файл принадлежит сущности
                        if (
                            $item && in_array($item, array_keys(\App\Domain\Types\FileItemType::LIST)) &&
                            $item_uuid && \Ramsey\Uuid\Uuid::isValid($item_uuid)
                        ) {
                            $file_model->item = $item;
                            $file_model->item_uuid = $item_uuid;
                        }

                        $this->entityManager->persist($file_model);

                        // is image
                        if (\Alksily\Support\Str::start('image/', $file_model->type)) {
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

        if ($models && $path_only) {
            $file = array_shift($models)[0] ?? false;

            if ($file) {
                /** @var \App\Domain\Entities\File $file */
                return $this->respondWithJson(['link' => $file->getPublicPath()]);
            }
        }

        return $this->respondWithJson($models);
    }
}
