<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File\Image;

use App\Application\Actions\Cup\File\FileAction;

class GetAction extends FileAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $result = [];

        foreach ($this->fileService->read() as $file) {
            /** @var \App\Domain\Entities\File $file */
            if (str_starts_with($file->getType(), 'image/')) {
                $result[] = [
                    'url' => $file->getPublicPath(),
                    'thumb' => $file->getPublicPath('small'),
                ];
            }
        }

        return $this->respondWithJson($result);
    }
}
