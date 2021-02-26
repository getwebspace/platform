<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Domain\AbstractTask;
use App\Domain\Service\File\Exception\FileNotFoundException;
use App\Domain\Service\File\FileService;

class ConvertImageTask extends AbstractTask
{
    public const TITLE = 'Обработка изображений';

    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        $default = [
            'uuid' => [\Ramsey\Uuid\Uuid::NIL],
        ];
        $params = array_merge($default, $params);

        return parent::execute($params);
    }

    protected function action(array $args = []): void
    {
        if ($this->parameter('image_enable', 'no') === 'no') {
            $this->setStatusCancel();

            return;
        }

        $convert_size = $this->parameter('image_convert_min_size', 100000);
        $command = $this->parameter('image_convert_bin', '/usr/bin/convert');
        $params = [
            '-quality 70%',
            '-filter Lanczos',
            '-gaussian-blur 0.05',
            '-sampling-factor 4:2:0',
            '-colorspace RGB',
            '-interlace Plane',
            '-strip',
            '-depth 8',
            '-в',
            '-background white',
            '-alpha remove',
            '-alpha off',
        ];
        if (($arg = $this->parameter('image_convert_args', false)) !== false) {
            $params = [$arg];
        }
        $params[] = '-set comment "Converted in WebSpace Engine CMS"';

        $fileService = FileService::getWithContainer($this->container);

        foreach ((array) $args['uuid'] as $index => $uuid) {
            try {
                $file = $fileService->read(['uuid' => $uuid]);

                if ($file->getSize() < $convert_size) {
                    $this->logger->info('Task: skip file via min size');
                    continue;
                }

                if (str_start_with($file->getType(), 'image/')) {
                    $folder = $file->getDir('');
                    $original = $file->getInternalPath();

                    foreach (
                        [
                            'middle' => $this->parameter('image_convert_size_middle', 450),
                            'small' => $this->parameter('image_convert_size_small', 200),
                        ] as $size => $pixels
                    ) {
                        if ($pixels > 0) {
                            $path = $folder . '/' . $size;

                            if (!file_exists($path)) {
                                mkdir($path, 0777, true);
                            }

                            $buf = array_merge($params, ['-resize x' . $pixels . '\>']);
                            @exec($command . " '" . $original . "' " . implode(' ', $buf) . " '" . $path . '/' . $file->getName() . ".jpg'");
                            $this->logger->info('Task: convert image', ['size' => $size, 'salt' => $file->getSalt(), 'params' => $buf]);
                        }
                    }

                    @exec($command . " '" . $original . "' " . implode(' ', $params) . " '" . $folder . '/' . $file->getName() . ".jpg'");
                    $this->logger->info('Task: convert image', ['size' => 'original', 'salt' => $file->getSalt(), 'params' => $params]);

                    // set file type and ext
                    if ($file->getExt() !== 'jpg') {
                        $file->setExt('jpg');
                        $file->setType('image/jpeg; charset=binary');
                    }

                    // update file size
                    $file->setSize(+filesize($folder . '/' . $file->getName() . '.jpg'));
                }
            } catch (FileNotFoundException $e) {
                $this->logger->alert('Task: file not found');
            }

            $this->setProgress($index, count($args['uuid']));
        }

        $this->setStatusDone();
    }
}
