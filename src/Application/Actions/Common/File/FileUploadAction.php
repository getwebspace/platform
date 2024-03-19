<?php declare(strict_types=1);

namespace App\Application\Actions\Common\File;

class FileUploadAction extends FileAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $path_only = $this->getParam('path_only', false);

        $models = [];

        if ($this->parameter('file_is_enabled', 'yes') === 'yes') {
            foreach ($this->request->getUploadedFiles() as $field => $files) {
                if (!is_array($files)) {
                    $files = [$files]; // allow upload one file
                }

                $uuids = [];
                foreach ($files as $file) {
                    if (!$file->getError()) {
                        $model = $this->fileService->createFromPath($file->getFilePath(), $file->getClientFilename());

                        // is image
                        if (str_starts_with($model->type, 'image/')) {
                            $uuids[] = $model->uuid;
                        }

                        $models[$field][] = $model;
                    }
                }

                if ($uuids) {
                    // add task convert
                    $task = new \App\Domain\Tasks\ConvertImageTask($this->container);
                    $task->execute(['uuid' => $uuids]);

                    // run worker
                    \App\Domain\AbstractTask::worker($task);
                }
            }

            $this->entityManager->flush();

            $this->container->get(\App\Application\PubSub::class)->publish('common:file:upload', $models);
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
