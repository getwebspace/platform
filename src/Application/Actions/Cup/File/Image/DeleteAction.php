<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File\Image;

use App\Application\Actions\Cup\File\FileAction;
use App\Domain\Service\File\FileService;

class DeleteAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $src = $this->request->getParam('src', false);

        if ($src !== false) {
            $fileService = FileService::getWithContainer($this->container);

            $info = pathinfo($src);

            $file = $fileService->read([
                'name' => str_escape($info['filename']),
                'ext' => str_escape($info['extension']),
            ]);

            if ($file) {
                $fileService->delete($file);
            }
        }

        return $this->respondWithJson(['status' => 'ok']);
    }
}
