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
            $params = [
                '-sampling-factor 4:2:0',
                '-strip',
                '-quality 75%',
                '-depth 8',
                '-define jpeg:extent=300k',
                '-interlace JPEG',
                '-colorspace RGB',
                '-background white',
                '-alpha remove',
                '-alpha off',
                '-set comment "Converted in WebSpace Engine CMS"',
            ];

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

                    $buf = array_merge($params, ['-resize x' . $pixels . '\>']);
                    @exec($command . " '" . $original . "' " . implode(' ', $buf) . " '" . $path . "/" . $file->name . ".jpg'");
                    $this->logger->info('Task: convert image', ['size' => $size, 'salt' => $file->salt, 'params' => $buf]);
                }
            }

            @exec($command . " '" . $original . "' " . implode(' ', $params) . " '" . $folder . "/" . $file->name . ".jpg'");
            $this->logger->info('Task: convert image', ['size' => 'original', 'salt' => $file->salt, 'params' => $params]);

            // установка расширения файла и типа
            if ($file->ext !== 'jpg') {
                $file->ext = 'jpg';
                $file->type = 'image/jpeg; charset=binary';
            }

            // обновление размера файла
            $file->size = filesize($folder . '/' . $file->name . '.jpg');
        }

        $this->status_done();
    }
}
