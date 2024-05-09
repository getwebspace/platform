<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File;

use App\Domain\Service\File\Exception\FileNotFoundException;

class FileDeleteAction extends FileAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $file = $this->fileService->read([
                    'uuid' => $this->resolveArg('uuid'),
                ]);

                if ($file) {
                    $this->fileService->delete($file);

                    $this->container->get(\App\Application\PubSub::class)->publish('cup:file:delete', $file);
                }
            } catch (FileNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/file');
    }
}
