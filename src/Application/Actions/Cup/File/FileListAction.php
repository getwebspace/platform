<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File;

class FileListAction extends FileAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/file/index.twig', [
            'list' => $this->fileService->read(['order' => ['name' => 'asc', 'date' => 'desc']]),
        ]);
    }
}
