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
            'uuid' => [],
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

        $fileService = $this->container->get(FileService::class);

        $convert_size = $this->parameter('image_convert_min_size', 100000);

        foreach ((array) $args['uuid'] as $index => $uuid) {
            try {
                /** @var File $file */
                $file = $fileService->read(['uuid' => $uuid]);
                $this->logger->info('Task: prepare convert', ['file' => $file->filename(), 'salt' => $file->salt]);

                if (str_starts_with($file->type, 'image/')) {
                    if ($file->size >= $convert_size) {
                        $folder = $file->dir();
                        $original = $folder . '/' . $file->name . '.orig';
                        $filesize = filesize($file->internal_path());

                        if (!file_exists($original)) {
                            @copy($file->internal_path(), $original);
                            $filesize += filesize($original);
                        }

                        $sizes = [
                            'big' => $this->parameter('image_convert_size_big', 960),
                            'middle' => $this->parameter('image_convert_size_middle', 450),
                            'small' => $this->parameter('image_convert_size_small', 200),
                        ];

                        // resize original picture
                        $this->resizeAndSaveImage($original, $folder . '/' . $file->name . '.webp');
                        $filesize += filesize($folder . '/' . $file->name . '.webp');

                        foreach ($sizes as $size => $pixels) {
                            if ($pixels > 0) {
                                $path = $folder . '/' . $size;

                                if (!file_exists($path)) {
                                    @mkdir($path, 0o777, true);
                                }

                                // resize specific size
                                $this->resizeAndSaveImage($original, $path . '/' . $file->name . '.webp', $pixels);
                                $filesize += filesize($path . '/' . $file->name . '.webp');
                            }
                        }

                        $file->update([
                            // set file ext and type
                            'ext' => 'webp',
                            'type' => 'image/webp',

                            // update file size
                            'size' => $filesize,
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

    /**
     * @param mixed $original
     * @param mixed $destination
     * @param null|mixed $maxHeight
     *
     * @throws \Exception
     */
    private function resizeAndSaveImage($original, $destination, $maxHeight = null): void
    {
        [$width, $height, $type] = getimagesize($original);

        // create image from
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($original);

                break;

            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($original);

                break;

            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($original);

                break;

            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($original);

                break;

            case IMAGETYPE_BMP:
                $source = imagecreatefrombmp($original);

                break;

            case IMAGETYPE_WBMP:
                $source = imagecreatefromwbmp($original);

                break;

            case IMAGETYPE_XBM:
                $source = imagecreatefromxbm($original);

                break;

            default:
                throw new \Exception('Unsupported image type');
        }

        // calculate new image size
        if ($maxHeight) {
            $ratio = $height / $maxHeight;
            $newWidth = intval($width / $ratio);
            $newHeight = intval($maxHeight);
        } else {
            $newWidth = intval($width);
            $newHeight = intval($height);
        }

        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // save as WebP
        imagewebp($thumbnail, $destination);

        // releasing memory
        imagedestroy($source);
        imagedestroy($thumbnail);
    }
}
