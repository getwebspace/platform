<?php

namespace Application\Actions\Common\File;

use Slim\Http\Response;

class FileGetAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        /* @var \Domain\Entities\File $file */
        $file = $this->fileRepository->findOneBy([
            'salt' => $this->resolveArg('salt'),
            'hash' => $this->resolveArg('hash'),
        ]);

        return $this->response
            ->withHeader('Content-Type', $file->type)
            ->withHeader('Content-Type', 'application/download')
            ->withHeader('Content-Description', 'File Transfer')
            ->withHeader('Content-Transfer-Encoding', 'binary')
            ->withHeader('Expires', '0')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $file->name . '"')
            ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
            ->withHeader('Pragma', 'public')
            ->withBody(new \Slim\Http\Stream($file->getResource()));
    }
}
