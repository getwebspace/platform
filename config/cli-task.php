<?php


// Include global const's
use App\Domain\Tasks\TradeMaster\CatalogSyncTask;

require __DIR__ . '/../src/bootstrap.php';

// App container
$c = $container = $app->getContainer();

// Set up dependencies
require __DIR__ . '/../app/dependencies.php';

$entityManager = $container->get(\Doctrine\ORM\EntityManager::class);

/** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $taskRepository */
$taskRepository = $entityManager->getRepository(\App\Domain\Entities\Task::class);

/** @var \App\Domain\Entities\Task $queue */
$queue = $taskRepository->findOneBy(['status' => \App\Domain\Types\TaskStatusType::STATUS_QUEUE], ['date' => 'asc']);

if ($queue) {
    /** @var \App\Domain\Tasks\Task $task */
    $task = new $queue->action($c, $queue);
    $task->run();

    register_shutdown_function(function () {
        passthru('php ' . CONFIG_DIR . '/cli-task.php');
    });
}
