<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File\Image;

use Alksily\Support\Str;
use App\Application\Actions\Cup\File\FileAction;

class GetAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $result = [];

        foreach ($this->fileRepository->findBy([], [], 1000) as $file) {
            /** @var \App\Domain\Entities\File $file */
            if (Str::start('image/', $file->type)) {
                $result[] = [
                    'url' => $file->getPublicPath(),
                    'thumb' => $file->getPublicPath('small'),
                ];
            }
        }

        return $this->respondWithJson($result);
    }
}
