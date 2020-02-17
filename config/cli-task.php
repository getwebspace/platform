<?php

require __DIR__ . '/../src/bootstrap.php';

// exit if another worker works
if (file_exists(\App\Domain\Tasks\Task::$pid_file)) exit;

// before work write self PID to file
file_put_contents(\App\Domain\Tasks\Task::$pid_file, getmypid());

// App container
$c = $container = $app->getContainer();

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

$entityManager = $container->get(\Doctrine\ORM\EntityManager::class);

/** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $taskRepository */
$taskRepository = $entityManager->getRepository(\App\Domain\Entities\Task::class);

/** @var \App\Domain\Entities\Task $queue */
$queue = $taskRepository->findOneBy(['status' => \App\Domain\Types\TaskStatusType::STATUS_QUEUE], ['date' => 'asc']);

if ($queue) {
    /** @var \App\Domain\Tasks\Task $task */
    $task = new $queue->action($c, $queue);
    $task->run();
}

// rerun worker
register_shutdown_function(function () use ($queue) {
    unlink(\App\Domain\Tasks\Task::$pid_file);

    if ($queue) {
        \App\Domain\Tasks\Task::worker();
    }
});
