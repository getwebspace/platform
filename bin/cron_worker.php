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

// one moment for the whole pass: a slow job does not shift the minute of the next one
$now = datetime();

// check jobs
foreach ($scheduler->get() as $name => $scheduled) {
    $schedule = $scheduled['schedule'];

    /** @var \App\Domain\AbstractSchedule $job */
    $job = $scheduled['job'];

    try {
        if (!$job->isShouldRun($schedule, $now)) {
            continue;
        }

        // a run that is still going is not started twice
        $lock = @fopen(VAR_DIR . '/cron-' . md5((string) $name) . '.lock', 'c');
        if ($lock !== false && !flock($lock, LOCK_EX | LOCK_NB)) {
            $logger->warning('Cron job skipped: the previous run is still going', ['job' => $name]);

            continue;
        }

        $started = microtime(true);
        $logger->info('Cron job start', ['job' => $name, 'schedule' => $schedule]);
        $job->run();
        $logger->info('Cron job done', ['job' => $name, 'seconds' => round(microtime(true) - $started, 3)]);
    } catch (Throwable $e) {
        $logger->error('Cron job failed', [
            'job' => $name,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    } finally {
        if (isset($lock) && is_resource($lock)) {
            fclose($lock); // releases the lock
        }
        unset($lock);
    }
}
