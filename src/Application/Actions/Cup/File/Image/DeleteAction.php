<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File\Image;

use App\Application\Actions\Cup\File\FileAction;

class DeleteAction extends FileAction
{
    protected function action(): \Slim\Http\Response
    {
        $src = $this->request->getParam('src', false);

        if ($src !== false) {
            $info = pathinfo($src);

            /** @var \App\Domain\Entities\File $file */
            $file = $this->fileRepository->findOneBy([
                'name' => str_escape($info['filename']),
                'ext' => str_escape($info['extension']),
            ]);

            if ($file) {
                $file->unlink();
                $this->entityManager->remove($file);
                $this->entityManager->flush();
            }
        }

        return $this->respondWithData(['status' => 'ok']);
    }
}
