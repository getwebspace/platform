<?php

namespace App\Domain\Tasks;

use AEngine\Support\Str;

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
            $sizeMiddle = '-resize x' . $this->getParameter('image_convert_size_middle', '450') . '\>';
            $sizeSmall = '-resize x' . $this->getParameter('image_convert_size_small', '200') . '\>';

            foreach (['full' => '', 'middle' => $sizeMiddle, 'small' => $sizeSmall] as $size => $options) {
                $path = $folder . '/' . $size;

                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                @exec($command . " '" . $original . "'" . ($options ? ' ' . $options : '') . " -set comment 'Converted in 0x12f CMS' " . $path . '/' . $file->name . '.jpg');
            }
        }

        $this->status_done();
    }
}
