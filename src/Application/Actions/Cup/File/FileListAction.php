<?php

namespace App\Application\Actions\Cup\File;

class FileListAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->fileRepository->findAll());

        return $this->respondRender('cup/file/index.twig', ['list' => $list]);
    }
}
