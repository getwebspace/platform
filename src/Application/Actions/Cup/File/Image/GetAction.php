<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File\Image;

use Alksily\Support\Str;
use App\Application\Actions\Cup\File\FileAction;
use App\Domain\Service\File\FileService;

class GetAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $fileService = FileService::getWithContainer($this->container);

        $result = [];

        foreach ($fileService->read() as $file) {
            /** @var \App\Domain\Entities\File $file */
            if (Str::start('image/', $file->getType())) {
                $result[] = [
                    'url' => $file->getPublicPath(),
                    'thumb' => $file->getPublicPath('small'),
                ];
            }
        }

        return $this->respondWithJson($result);
    }
}
