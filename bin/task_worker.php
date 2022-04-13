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

// bind error/exception handler
set_error_handler(ErrorHandler($container));
set_exception_handler(ExceptionHandler($container));

/** @var \Monolog\Logger $logger */
$logger = $container->get(\Psr\Log\LoggerInterface::class);

/** @var \App\Domain\Service\Task\TaskService $taskService */
$taskService = $container->get(\App\Domain\Service\Task\TaskService::class);

/** @var \Illuminate\Support\Collection $queue */
$queue = $taskService->read([
    'action' => $action,
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
register_shutdown_function(function () use ($queue, $action): void {
    sleep(1); // timeout

    // after work remove PID file
    \App\Domain\AbstractTask::workerRemovePidFile($action);

    if ($queue->count()) {
        \App\Domain\AbstractTask::worker($_SERVER['argv'][1] ?? '');
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
        }
    } catch (Exception $e) {
        $logger->error('Task catch exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'code' => $e->getCode(),
        ]);
    }
}
