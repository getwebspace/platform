<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File;

use App\Domain\Service\File\FileService;

class FileDeleteAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $fileService = FileService::getWithContainer($this->container);
            $fileService->delete($this->resolveArg('uuid'));
        }

        return $this->response->withRedirect('/cup/file');
    }
}
