<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Domain\AbstractTask;
use App\Domain\Models\File;
use App\Domain\Service\File\Exception\FileNotFoundException;
use App\Domain\Service\File\FileService;

class ConvertImageTask extends AbstractTask
{
    public const TITLE = 'Image processing';

    public function execute(array $params = []): \App\Domain\Models\Task
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
        if ($this->parameter('image_enable', 'yes') === 'no') {
            $this->setStatusCancel();

            return;
        }

        $convert_size = $this->parameter('image_convert_min_size', 100000);
        $command = $this->parameter('image_convert_bin', '/usr/bin/convert');
        $params = [
            '-quality 80%',
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
                $this->logger->info('Task: prepare convert', ['file' => $file->filename(), 'salt' => $file->salt]);

                if (str_starts_with($file->type, 'image/')) {
                    if ($file->size >= $convert_size) {
                        $folder = $file->dir();
                        $original = $folder . '/' . $file->name . '.orig';

                        if (!file_exists($original)) {
                            @copy($file->internal_path(), $original);
                        }

                        $log = [];
                        $sizes = [
                            'big' => $this->parameter('image_convert_size_big', 960),
                            'middle' => $this->parameter('image_convert_size_middle', 450),
                            'small' => $this->parameter('image_convert_size_small', 200),
                        ];

                        foreach ($sizes as $size => $pixels) {
                            if ($pixels > 0) {
                                $path = $folder . '/' . $size;
                                $buf = array_merge($params, ['-resize x' . $pixels . '\>']);
                                $log[$size] = 'convert';

                                if (!file_exists($path)) {
                                    @mkdir($path, 0o777, true);
                                }
                                @exec($command . " '" . $original . "' " . implode(' ', $buf) . " '" . $path . '/' . $file->name . ".webp'");
                            }
                        }

                        @exec($command . " '" . $original . "' " . implode(' ', $params) . " '" . $folder . '/' . $file->name . ".webp'");
                        $log['original'] = 'convert';
                        $this->logger->info('Task: convert image', array_merge($log, ['params' => $params]));

                        $file->update([
                            // set file ext and type
                            'ext' => 'webp',
                            'type' => 'image/webp',

                            // update file size
                            'size' => +filesize($original),
                        ]);
                    } else {
                        $this->logger->info('Task: convert skipped, small file size');
                    }
                } else {
                    $this->logger->info('Task: convert skipped, is not image');
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
