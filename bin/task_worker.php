<?php declare(strict_types=1);

ini_set('memory_limit', '-1'); // fix memory usage

require __DIR__ . '/../src/bootstrap.php';

// bind error handler
error_reporting(E_ALL);
set_error_handler(function (int $code, string $message, string $file, int $line): bool {
    if ($code === E_DEPRECATED || $code === E_USER_DEPRECATED) {
        error_log("Deprecated: {$message} in {$file} on line {$line}");

        return true;
    }

    throw new \ErrorException($message, 0, $code, $file, $line);
});

$action = $_SERVER['argv'][1] ?? null;

// exit if another worker works (the lock is atomic, two starts at once give one process)
if (!\App\Domain\AbstractTask::workerLock((string) $action)) {
    exit;
}

/**
 * @var \Slim\App $app
 */

// app container
$container = $app->getContainer();

/** @var \Monolog\Logger $logger */
$logger = $container->get(\Psr\Log\LoggerInterface::class);

/** @var \App\Domain\Service\Task\TaskService $taskService */
$taskService = $container->get(\App\Domain\Service\Task\TaskService::class);

/** @var \Illuminate\Support\Collection $queue */
$queue = $taskService->read([
    'action' => $action,
    'status' => [
        \App\Domain\Casts\Task\Status::QUEUE,
        \App\Domain\Casts\Task\Status::WORK,
    ],
    'order' => [
        'date' => 'asc',
        'status' => 'desc',
    ],
    'limit' => 1,
]);

// rerun worker
register_shutdown_function(function () use ($queue, $action): void {
    // after work release the lock
    \App\Domain\AbstractTask::workerUnlock((string) $action);

    sleep(1); // timeout

    if ($queue->count()) {
        \App\Domain\AbstractTask::worker($action);
    }
});

if ($queue->count()) {
    /** @var \App\Domain\Models\Task $entity */
    $entity = $queue->first();
    $action = $entity->action;

    if (class_exists($action)) {
        /** @var \App\Domain\AbstractTask $task */
        $task = new $action($container, $entity);

        try {
            if ($entity->status === \App\Domain\Casts\Task\Status::QUEUE) {
                $task->run();
            } else {
                // remove a task that gave no sign of life for too long
                if (\App\Domain\AbstractTask::isStuck($entity) && !\App\Domain\AbstractTask::workerIsRunning($action)) {
                    $task->setStatusDelete('Removed by time');
                    $logger->info('Task removed by time', [
                        'uuid' => $entity->uuid,
                        'action' => $action,
                    ]);
                } else {
                    sleep(5);
                }
            }
        } catch (Throwable $e) {
            $task->setStatusFail($e->getMessage());
            $logger->error('Task catch exception', [
                'uuid' => $entity->uuid,
                'action' => $action,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ]);
        }
    } else {
        $taskService->update($entity, [
            'status' => \App\Domain\Casts\Task\Status::DELETE,
            'output' => 'Task class not found',
        ]);
        $logger->warning('Task class not found', [
            'uuid' => $entity->uuid,
            'action' => $action,
        ]);
    }
}
