<?php

namespace Application\Actions\Cup\File;

use Slim\Http\Response;

class FileListAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->fileRepository->findAll());

        return $this->respondRender('cup/file/index.twig', ['list' => $list]);
    }
}