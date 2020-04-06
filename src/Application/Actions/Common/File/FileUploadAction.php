<?php declare(strict_types=1);

namespace App\Application\Actions\Common\File;

use Slim\Http\UploadedFile;

class FileUploadAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $path_only = $this->request->getParam('path_only', false);

        $models = [];

        if ($this->getParameter('file_is_enabled', 'no') === 'yes') {
            foreach ($this->request->getUploadedFiles() as $field => $files) {
                if (!is_array($files)) {
                    $files = [$files];
                }

                // @var UploadedFile $file
                foreach ($files as $file) {
                    if (!$file->getError()) {
                        if (($model = \App\Domain\Entities\File::getFromPath($file->file, $file->getClientFilename())) !== null) {
                            $this->entityManager->persist($model);

                            // is image
                            if (\Alksily\Support\Str::start('image/', $model->type)) {
                                // add task convert
                                $task = new \App\Domain\Tasks\ConvertImageTask($this->container);
                                $task->execute(['uuid' => $model->uuid]);

                                // run worker
                                \App\Domain\Tasks\Task::worker();
                            }

                            // save model
                            $models[$field][] = $model;
                        }
                    }
                }
            }

            $this->entityManager->flush();
        }

        if ($models && $path_only) {
            $file = array_shift($models)[0] ?? false;

            if ($file) {
                // @var \App\Domain\Entities\File $file
                return $this->respondWithJson(['link' => $file->getPublicPath()]);
            }
        }

        return $this->respondWithJson($models);
    }
}
