<?php

namespace App\Application\Actions\Cup\File;

class FileDeleteAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \App\Domain\Entities\File $file */
            $file = $this->fileRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$file->isEmpty()) {
                $file->unlink();
                $this->entityManager->remove($file);
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/file')->withStatus(301);
    }
}
