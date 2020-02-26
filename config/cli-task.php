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

/** @var \Doctrine\ORM\EntityManager $entityManager */
$entityManager = $container->get(\Doctrine\ORM\EntityManager::class);

/** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $taskRepository */
$taskRepository = $entityManager->getRepository(\App\Domain\Entities\Task::class);

/** @var \App\Domain\Entities\Task $queue */
$queue = $taskRepository->findOneBy(['status' => [\App\Domain\Types\TaskStatusType::STATUS_QUEUE, \App\Domain\Types\TaskStatusType::STATUS_WORK]], ['date' => 'asc']);

// rerun worker
register_shutdown_function(function () use ($queue) {
    @unlink(\App\Domain\Tasks\Task::$pid_file);

    if ($queue) {
        \App\Domain\Tasks\Task::worker();
    }
});

if ($queue) {
    /** @var \App\Domain\Tasks\Task $task */
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
}
