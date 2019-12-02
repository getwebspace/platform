<?php

namespace App\Domain\Tasks;

use Alksily\Support\Str;

class ConvertImageTask extends Task
{
    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        $default = [
            'uuid' => \Ramsey\Uuid\Uuid::NIL,
        ];
        $params = array_merge($default, $params);

        return parent::execute($params);
    }

    protected function action(array $args = [])
    {
        /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $fileRepository */
        $fileRepository = $this->entityManager->getRepository(\App\Domain\Entities\File::class);

        /** @var \App\Domain\Entities\File $file */
        $file = $fileRepository->findOneBy(['uuid' => $args['uuid']]);

        if ($file && Str::start('image/', $file->type)) {
            $folder = $file->getDir('');
            $original = $file->getInternalPath();

            $command = $this->getParameter('image_convert_bin', '/usr/bin/convert');
            $params = "-background white -alpha remove -alpha off -set comment 'Converted in 0x12f CMS'";

            foreach (
                [
                    'middle' => $this->getParameter('image_convert_size_middle', 450),
                    'small' => $this->getParameter('image_convert_size_small', 200),
                ] as $size => $pixels
            ) {
                if ($pixels > 0) {
                    $path = $folder . '/' . $size;

                    if (!file_exists($path)) {
                        mkdir($path, 0777, true);
                    }

                    $this->logger->info('Task: convert image', ['size' => $size, 'pixels' => $pixels]);
                    @exec($command . " '" . $original . "' -resize x" . $pixels . "\> " . $params . " '" . $path . "/" . $file->name . ".jpg'");
                }
            }

            $this->logger->info('Task: convert image', ['size' => 'original',]);
            @exec($command . " '" . $original . "' " . $params . " '" . $folder . "/" . $file->name . ".jpg'");

            // установка расширения файла и типа
            if ($file->ext !== 'jpg') {
                $file->ext = 'jpg';
                $file->type = 'image/jpeg; charset=binary';
            }
        }

        $this->status_done();
    }
}
