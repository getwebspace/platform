<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File;

use App\Domain\Service\File\FileService;

class FileListAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $fileService = FileService::getWithContainer($this->container);
        $list = $fileService->read();

        return $this->respondWithTemplate('cup/file/index.twig', ['list' => $list]);
    }
}
