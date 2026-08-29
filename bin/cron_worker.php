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

/**
 * @var \Slim\App $app
 */

// app container
$container = $app->getContainer();

/** @var \Monolog\Logger $logger */
$logger = $container->get(\Psr\Log\LoggerInterface::class);

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
