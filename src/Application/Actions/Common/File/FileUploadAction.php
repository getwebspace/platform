<?php declare(strict_types=1);

namespace App\Application\Actions\Common\File;

class FileUploadAction extends FileAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $models = $this->getUploadedFiles(array_key_first($_FILES));
        $path_only = $this->getParam('path_only', false);

        if ($models && $path_only) {
            $file = array_shift($models)[0] ?? false;

            if ($file) {
                /** @var \App\Domain\Models\File $file */
                return $this->respondWithJson(['link' => $file->public_path()]);
            }
        }

        return $this->respondWithJson($models);
    }
}
