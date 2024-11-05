#!/usr/local/bin/php
<?php declare(strict_types=1);

ini_set('memory_limit', '-1'); // fix memory usage

require __DIR__ . '/../src/bootstrap.php';

$action = $_SERVER['argv'][1] ?? null;

// exit if another worker works
if (\App\Domain\AbstractTask::workerHasPidFile($action)) {
    exit;
}

// before work write self PID to file
\App\Domain\AbstractTask::workerCreatePidFile($action);

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

// bind error handler
error_reporting(E_ALL);
set_error_handler(function ($code, $message, $file, $line) {
    throw new \ErrorException($message, 0, $code, $file, $line);
});

// rerun worker
register_shutdown_function(function () use ($queue, $action): void {
    // after work remove PID file
    \App\Domain\AbstractTask::workerRemovePidFile($action);

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
                // remove task by time
                if (datetime()->diff($entity->date)->i >= 10) {
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
