<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File\Image;

use App\Application\Actions\Cup\File\FileAction;
use App\Domain\Service\File\Exception\FileNotFoundException;

class DeleteAction extends FileAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $src = $this->getParam('src', false);

        if ($src !== false) {
            $info = pathinfo($src);

            try {
                $file = $this->fileService->read([
                    'name' => str_escape($info['filename']),
                    'ext' => str_escape($info['extension']),
                ]);

                if ($file) {
                    $this->fileService->delete($file);

                    $this->container->get(\App\Application\PubSub::class)->publish('cup:file:delete', $file);
                }

                return $this->respondWithJson(['status' => 'ok']);
            } catch (FileNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithJson(['status' => 'not found']);
    }
}
