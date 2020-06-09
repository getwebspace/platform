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
        $fileService = FileService::getWithContainer($this->container);

        foreach ((array) $args['uuid'] as $index => $uuid) {
            try {
                $file = $fileService->read(['uuid' => $uuid]);
                $this->logger->info('Task: info', $file->toArray());

                if (str_starts_with('image/', $file->getType())) {
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
                            @exec($command . " '" . $original . "' " . implode(' ', $buf) . " '" . $path . '/' . $file->getName() . ".jpg'");
                            $this->logger->info('Task: convert image', ['size' => $size, 'salt' => $file->getSalt(), 'params' => $buf]);
                        }
                    }

                    @exec($command . " '" . $original . "' " . implode(' ', $params) . " '" . $folder . '/' . $file->getName() . ".jpg'");
                    $this->logger->info('Task: convert image', ['size' => 'original', 'salt' => $file->getSalt(), 'params' => $params]);

                    // установка расширения файла и типа
                    if ($file->getExt() !== 'jpg') {
                        $file->setExt('jpg');
                        $file->setType('image/jpeg; charset=binary');
                    }

                    // обновление размера файла
                    $file->setSize(filesize($folder . '/' . $file->getName() . '.jpg'));
                }
            } catch (FileNotFoundException $e) {
                $this->logger->alert('Task: file not found');
            }

            $this->setProgress($index, count($args['uuid']));
        }

        $this->setStatusDone();
    }
}
