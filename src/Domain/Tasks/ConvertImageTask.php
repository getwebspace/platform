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
            $params = array_map('trim', explode(PHP_EOL, $arg));
        }
        $params[] = '-set comment "Converted in WebSpace Engine CMS"';

        $fileService = $this->container->get(FileService::class);

        foreach ((array) $args['uuid'] as $index => $uuid) {
            try {
                $file = $fileService->read(['uuid' => $uuid]);
                $this->logger->info('Task: prepare convert', ['file' => $file->getFileName(), 'salt' => $file->getSalt()]);

                if ($file->getSize() >= $convert_size) {
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

                                if (!file_exists($path . '/' . $file->getName() . '.jpg')) {
                                    $buf = array_merge($params, ['-resize x' . $pixels . '\>']);
                                    $this->logger->info('Task: convert image', ['size' => $size, 'params' => $buf]);

                                    @mkdir($path, 0o777, true);
                                    @exec($command . " '" . $original . "' " . implode(' ', $buf) . " '" . $path . '/' . $file->getName() . ".jpg'");
                                } else {
                                    $this->logger->info('Task: skip, converted file already exists');
                                }
                            }
                        }

                        @exec($command . " '" . $original . "' " . implode(' ', $params) . " '" . $folder . '/' . $file->getName() . ".jpg'");
                        $this->logger->info('Task: convert image', ['size' => 'original', 'params' => $params]);

                        // set file type and ext
                        if ($file->getExt() !== 'jpg') {
                            $file->setExt('jpg');
                            $file->setType('image/jpeg; charset=binary');
                        }

                        // update file size
                        $file->setSize(+filesize($folder . '/' . $file->getName() . '.jpg'));
                    }
                } else {
                    $this->logger->info('Task: convert skipped, small file size');
                }
            } catch (FileNotFoundException $e) {
                $this->logger->alert('Task: file not found');
            }

            $this->setProgress($index, count($args['uuid']));
        }

        $this->setStatusDone();
    }
}
