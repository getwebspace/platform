<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File\Image;

use App\Application\Actions\Cup\File\FileAction;
use App\Domain\Service\File\Exception\FileNotFoundException;

class DeleteAction extends FileAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $src = $this->getParam('src', false);

        // /uploads/<folder>/[big|middle|small/]<name>.<ext>: the folder and the name together name one file
        if (is_string($src) && preg_match('#/uploads/([A-Za-z0-9]+)/(?:[a-z]+/)?([^/]+)\.([A-Za-z0-9]+)$#', (string) parse_url($src, PHP_URL_PATH), $m) === 1) {
            try {
                $file = $this->fileService->read(['salt' => $m[1], 'name' => str_escape($m[2]), 'ext' => mb_strtolower($m[3])]);

                // this is the picture manager of the text editor: it deletes pictures, not documents
                if (str_starts_with((string) $file->type, 'image/')) {
                    $this->fileService->delete($file);
                    $this->container->get(\App\Application\PubSub::class)->publish('cup:file:delete', $file);

                    return $this->respondWithJson(['status' => 'ok']);
                }
            } catch (FileNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithJson(['status' => 'not found']);
    }
}
