<?php declare(strict_types=1);

ini_set('memory_limit', '-1'); // fix memory usage

require __DIR__ . '/../src/bootstrap.php';

/**
 * @var \Slim\App $app
 */

// app container
$container = $app->getContainer();

/** @var \Monolog\Logger $logger */
$logger = $container->get(\Psr\Log\LoggerInterface::class);

// bind error handler
error_reporting(E_ALL);
set_error_handler(function ($code, $message, $file, $line) {
    throw new \ErrorException($message, 0, $code, $file, $line);
});

// simple scheduler
$scheduler = $container->get('scheduler');

// add jobs
// $scheduler->register(\App\Domain\Schedules\Test::class, '*/5 * * * *');

// check jobs
foreach ($scheduler->get() as $scheduled) {
    $schedule = $scheduled['schedule'];

    /** @var \App\Domain\AbstractSchedule $job */
    $job = $scheduled['job'];

    if ($job->isShouldRun($schedule)) {
        $job->run();
    }
}
