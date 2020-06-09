<?php declare(strict_types=1);

namespace App\Application\Actions\Common\File;

use App\Domain\Service\File\FileService;

class FileGetAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $fileService = FileService::getWithContainer($this->container);

        // @var \App\Domain\Entities\File $file
        $file = $fileService->read([
            'salt' => $this->resolveArg('salt'),
            'hash' => $this->resolveArg('hash'),
        ]);

        return $this->response
            ->withHeader('Content-Type', $file->getType())
            ->withHeader('Content-Type', 'application/download')
            ->withHeader('Content-Description', 'File Transfer')
            ->withHeader('Content-Transfer-Encoding', 'binary')
            ->withHeader('Expires', '0')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $file->getFileName() . '"')
            ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
            ->withHeader('Pragma', 'public')
            ->withBody(new \Slim\Http\Stream($file->getResource()));
    }
}
