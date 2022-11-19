<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Domain\AbstractTask;
use App\Domain\Entities\File;
use App\Domain\Service\File\Exception\FileNotFoundException;
use App\Domain\Service\File\FileService;

class ConvertImageTask extends AbstractTask
{
    public const TITLE = 'Image processing';

    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        $default = [
            'uuid' => [\Ramsey\Uuid\Uuid::NIL],
        ];
        $params = array_merge($default, $params);

        return parent::execute($params);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \App\Domain\Service\Task\Exception\TaskNotFoundException
     */
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
            // '-define webp:lossless=true',
        ];
        if (($arg = $this->parameter('image_convert_args', false)) !== false) {
            $params = array_map('trim', explode(PHP_EOL, $arg));
        }
        $params[] = '-set comment "Converted in WebSpace Engine CMS"';

        $fileService = $this->container->get(FileService::class);

        foreach ((array) $args['uuid'] as $index => $uuid) {
            try {
                /** @var File $file */
                $file = $fileService->read(['uuid' => $uuid]);
                $this->logger->info('Task: prepare convert', ['file' => $file->getFileName(), 'salt' => $file->getSalt()]);

                if ($file->getSize() >= $convert_size) {
                    if (str_start_with($file->getType(), 'image/')) {
                        $folder = $file->getDir('');
                        $original = '';

                        // search original image
                        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
                            $buf = $folder . '/' . $file->getName() . '.' . $ext;
                            if (file_exists($buf)) {
                                $original = $buf;
                                break;
                            }
                        }
                        if (!$original) {
                            $this->logger->info('Task: skip convert, original file not found');
                            continue;
                        }

                        $log = [];

                        foreach (
                            [
                                'middle' => $this->parameter('image_convert_size_middle', 450),
                                'small' => $this->parameter('image_convert_size_small', 200),
                            ] as $size => $pixels
                        ) {
                            if ($pixels > 0) {
                                $path = $folder . '/' . $size;
                                $buf = array_merge($params, ['-resize x' . $pixels . '\>']);
                                $log[$size] = 'convert';

                                @mkdir($path, 0o777, true);
                                @exec($command . " '" . $original . "' " . implode(' ', $buf) . " '" . $path . '/' . $file->getName() . ".webp'");
                            }
                        }

                        @exec($command . " '" . $original . "' " . implode(' ', $params) . " '" . $folder . '/' . $file->getName() . ".webp'");
                        $log['original'] = 'convert';
                        $this->logger->info('Task: convert image', array_merge($log, ['params' => $params]));

                        // set file ext and type
                        $file->setExt('webp');
                        $file->setType('image/webp');

                        // update file size
                        $file->setSize(+filesize($original));
                    }
                } else {
                    $this->logger->info('Task: convert skipped, small file size');
                }
            } catch (FileNotFoundException $e) {
                $this->logger->alert('Task: file not found', ['message' => $e->getMessage()]);
            }

            $this->setProgress($index, count($args['uuid']));
        }

        $this->container->get(\App\Application\PubSub::class)->publish('task:file:convert');

        $this->setStatusDone();
    }
}
