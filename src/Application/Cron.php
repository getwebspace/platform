<?php declare(strict_types=1);

namespace App\Application;

use App\Domain\AbstractSchedule;
use App\Domain\Service\Schedule\ScheduleService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * One pass of the scheduler (bin/cron_worker.php runs it every minute)
 */
class Cron
{
    /**
     * consecutive failures after which the others get to know
     */
    public const FAILURES_NOTICE = 3;

    private ContainerInterface $container;

    private object $scheduler;

    /**
     * @param null|object $scheduler the registered jobs (get(), make()); the container's by default
     */
    public function __construct(ContainerInterface $container, ?object $scheduler = null)
    {
        $this->container = $container;
        $this->scheduler = $scheduler ?? $container->get('scheduler');
    }

    public function pass(?\DateTime $at = null): void
    {
        $logger = $this->container->get(LoggerInterface::class);
        $scheduler = $this->scheduler;
        $states = $this->container->get(ScheduleService::class);
        $pubsub = $this->container->get(PubSub::class);

        // one moment for the whole pass: a slow job does not shift the minute of the next one
        $now = datetime($at);
        $minute = $now->copy()->startOfMinute();

        foreach ($scheduler->get() as $name => $scheduled) {
            $schedule = $scheduled['schedule'];
            $lock = false;

            try {
                $state = $states->read(['key' => $name]);

                if ($state->schedule !== $schedule) {
                    $state = $states->update($state, ['schedule' => $schedule]);
                }

                $runNow = $state->run_now;

                // the latest scheduled moment up to now: a job runs when it has not run since that moment
                $due = AbstractSchedule::lastDue($schedule, $now);
                $notRunYet = $state->last_run === null || $state->last_run < $due;
                $isDue = $due->format('Y-m-d H:i') === $minute->format('Y-m-d H:i');
                // after a downtime: only a job that ran before and asked for it, once however many runs were missed
                $missed = $scheduled['catch_up'] && $state->last_run !== null && $notRunYet;

                if (!$runNow && (!$state->enabled || !$notRunYet || !($isDue || $missed))) {
                    continue;
                }

                // a run that is still going is not started twice
                $lockPath = VAR_DIR . '/cron-' . md5((string) $name) . '.lock';
                $lockNew = !file_exists($lockPath);
                $lock = @fopen($lockPath, 'ce'); // 'e': a worker started by the job does not inherit it
                if ($lock === false) {
                    $logger->warning('Cron job runs without a lock: the lock file cannot be opened', ['job' => $name, 'file' => $lockPath]);
                } elseif ($lockNew) {
                    @chmod($lockPath, 0o666);
                }
                if ($lock !== false && !flock($lock, LOCK_EX | LOCK_NB)) {
                    $logger->warning('Cron job skipped: the previous run is still going', ['job' => $name]);

                    continue;
                }

                $started = microtime(true);
                $state = $states->update($state, ['last_run' => $now, 'status' => 'running', 'run_now' => false]);
                $logger->info('Cron job start', ['job' => $name, 'schedule' => $schedule, 'catch_up' => $missed && !$isDue, 'manual' => $runNow]);

                $error = null;

                try {
                    $scheduler->make($scheduled)->run();
                } catch (\Throwable $e) {
                    $error = $e;
                }

                $seconds = round(microtime(true) - $started, 3);

                if ($error === null) {
                    $states->update($state, ['status' => 'ok', 'error' => null, 'duration' => $seconds, 'failures' => 0, 'last_success' => datetime()]);
                    $logger->info('Cron job done', ['job' => $name, 'seconds' => $seconds]);
                } else {
                    $state = $states->update($state, ['status' => 'fail', 'error' => $error->getMessage(), 'duration' => $seconds, 'failures' => $state->failures + 1]);
                    $logger->error('Cron job failed', [
                        'job' => $name,
                        'message' => $error->getMessage(),
                        'file' => $error->getFile(),
                        'line' => $error->getLine(),
                    ]);

                    if ($state->failures === self::FAILURES_NOTICE) {
                        $logger->critical('Cron job fails again and again', ['job' => $name, 'failures' => $state->failures]);
                        $pubsub->publish('cron:job:failed', ['job' => $name, 'failures' => $state->failures, 'error' => $error->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                // the bookkeeping itself failed: the next job still gets its turn
                $logger->error('Cron job not processed', ['job' => $name, 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            } finally {
                if (is_resource($lock)) {
                    flock($lock, LOCK_UN);
                    fclose($lock);
                }
            }
        }
    }
}
