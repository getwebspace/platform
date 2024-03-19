<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File\Image;

use App\Application\Actions\Cup\File\FileAction;

class GetAction extends FileAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $result = [];

        foreach ($this->fileService->read() as $file) {
            /** @var \App\Domain\Models\File $file */
            if (str_starts_with($file->type, 'image/')) {
                $result[] = [
                    'url' => $file->public_path(),
                    'thumb' => $file->public_path('small'),
                ];
            }
        }

        return $this->respondWithJson($result);
    }
}
