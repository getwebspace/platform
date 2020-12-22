<?php declare(strict_types=1);

ini_set('memory_limit', '-1'); // fix memory usage

require __DIR__ . '/../src/bootstrap.php';

// exit if another worker works
if (file_exists(\App\Domain\AbstractTask::$pid_file)) {
    exit;
}

// before work write self PID to file
file_put_contents(\App\Domain\AbstractTask::$pid_file, getmypid());

/**
 * @var \Slim\App $app
 */

// app container
$container = $app->getContainer();

/** @var \Monolog\Logger $logger */
$logger = $container->get('monolog');

/** @var \App\Domain\Service\Task\TaskService $taskService */
$taskService = \App\Domain\Service\Task\TaskService::getWithContainer($container);

/** @var \Illuminate\Support\Collection $queue */
$queue = $taskService->read([
    'status' => [
        \App\Domain\Types\TaskStatusType::STATUS_QUEUE,
        \App\Domain\Types\TaskStatusType::STATUS_WORK,
    ],
    'order' => [
        'date' => 'asc',
    ],
    'limit' => 1,
]);

// rerun worker
register_shutdown_function(function () use ($queue): void {
    @unlink(\App\Domain\AbstractTask::$pid_file);

    sleep(1); // timeout

    if ($queue->count()) {
        \App\Domain\AbstractTask::worker();
    }
});

if ($queue->count()) {
    /** @var \App\Domain\Entities\Task $entity */
    $entity = $queue->first();
    $action = $entity->getAction();

    try {
        if (class_exists($action)) {
            /** @var \App\Domain\AbstractTask $task */
            $task = new $action($container, $entity);

            if ($entity->getStatus() === \App\Domain\Types\TaskStatusType::STATUS_QUEUE) {
                $task->run();
            } else {
                // remove task by time
                if ((new DateTime())->diff($entity->getDate())->i >= 10) {
                    $task->setStatusDelete('Removed by time');
                } else {
                    sleep(30);
                }
            }
        } else {
            $taskService->update($entity, [
                'status' => \App\Domain\Types\TaskStatusType::STATUS_DELETE,
                'output' => 'Task class not found',
            ]);
            sleep(30);
        }
    } catch (Exception $e) {
        $logger->error('Task catch exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'code' => $e->getCode(),
        ]);
    }
}
