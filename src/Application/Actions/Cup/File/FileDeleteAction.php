<?php

namespace Application\Actions\Cup\File;

use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class FileDeleteAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \Domain\Entities\File $item */
            $item = $this->fileRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty() && $this->request->isPost()) {
                $this->entityManager->remove($item);
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/file');
    }
}
