<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File;

class FileListAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->fileRepository->findAll());

        return $this->respondWithTemplate('cup/file/index.twig', ['list' => $list]);
    }
}
