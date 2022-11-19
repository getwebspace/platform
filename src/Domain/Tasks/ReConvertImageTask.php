<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Domain\AbstractTask;
use App\Domain\Entities\File;
use App\Domain\Service\File\FileService;

class ReConvertImageTask extends AbstractTask
{
    public const TITLE = 'Re process images';

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

        $fileService = $this->container->get(FileService::class);

        $uuids = [];
        foreach ($fileService->read() as $file) {
            /** @var File $file */
            if (str_start_with($file->getType(), 'image/')) {
                $uuids[] = $file->getUuid()->toString();
            }
        }

        // add task convert
        $task = new \App\Domain\Tasks\ConvertImageTask($this->container);
        $task->execute(['uuid' => $uuids]);

        // run worker
        \App\Domain\AbstractTask::worker($task);

        $this->setStatusDone();
    }
}
