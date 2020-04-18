<?php declare(strict_types=1);

ini_set('memory_limit', '-1'); // fix memory usage

require __DIR__ . '/../src/bootstrap.php';

// exit if another worker works
if (file_exists(\App\Domain\AbstractTask::$pid_file)) {
    exit;
}

// before work write self PID to file
file_put_contents(\App\Domain\AbstractTask::$pid_file, getmypid());

// App container
$c = $container = $app->getContainer();

/** @var \Monolog\Logger $logger */
$logger = $container->get('monolog');

/** @var \Doctrine\ORM\EntityManager $entityManager */
$entityManager = $container->get(\Doctrine\ORM\EntityManager::class);

/** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $taskRepository */
$taskRepository = $entityManager->getRepository(\App\Domain\Entities\Task::class);

/** @var \App\Domain\Entities\Task $queue */
$queue = $taskRepository->findOneBy(['status' => [\App\Domain\Types\TaskStatusType::STATUS_QUEUE, \App\Domain\Types\TaskStatusType::STATUS_WORK]], ['date' => 'asc']);

// rerun worker
register_shutdown_function(function () use ($queue): void {
    @unlink(\App\Domain\AbstractTask::$pid_file);

    sleep(3); // timeout

    if ($queue) {
        \App\Domain\AbstractTask::worker();
    }
});

if ($queue) {
    try {
        /** @var \App\Domain\AbstractTask $task */
        $task = new $queue->action($c, $queue);

        if ($queue->status === \App\Domain\Types\TaskStatusType::STATUS_QUEUE) {
            $task->run();
        } else {
            // удаление задачи по времени
            if ((new DateTime())->diff($queue->date)->i >= 30) {
                $task->setStatusDelete();
                $entityManager->flush();
            } else {
                sleep(30);
            }
        }
    } catch (Exception $e) {
        $logger->error('Task catch exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'code' => $e->getCode(),
        ]);
    }
}
