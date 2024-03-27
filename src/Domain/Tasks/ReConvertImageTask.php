<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Domain\AbstractTask;
use App\Domain\Service\File\FileService;

class ReConvertImageTask extends AbstractTask
{
    public const TITLE = 'Re process images';

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

        $fileService = $this->container->get(FileService::class);

        // add task convert
        $task = new \App\Domain\Tasks\ConvertImageTask($this->container);
        $task->execute([
            'uuid' => $fileService
                ->read()
                ->filter(fn (\App\Domain\Models\File $file) => str_starts_with($file->type, 'image/'))
                ->pluck('uuid'),
        ]);

        // run worker
        \App\Domain\AbstractTask::worker($task);

        $this->setStatusDone();
    }
}
