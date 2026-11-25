<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Service\Task\TaskService;
use App\Domain\Traits\HasParameters;
use Cron\CronExpression;
use Illuminate\Cache\ArrayStore as ArrayCache;
use Illuminate\Cache\FileStore as FileCache;
use Illuminate\Database\Connection as DataBase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractSchedule
{
    use HasParameters;

    protected ContainerInterface $container;

    protected LoggerInterface $logger;

    protected DataBase $db;

    protected ArrayCache $arrayCache;

    protected FileCache $fileCache;

    private TaskService $taskService;

    /**
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerInterface::class);
        $this->db = $container->get(DataBase::class);
        $this->arrayCache = $container->get(ArrayCache::class);
        $this->fileCache = $container->get(FileCache::class);
        $this->taskService = $container->get(TaskService::class);
    }

    abstract public function run();

    /**
     * Is the cron line valid: five fields, `@daily` and the like, `MON`-`SUN`, ranges with steps
     */
    public static function isValid(mixed $schedule): bool
    {
        return is_string($schedule) && CronExpression::isValidExpression(trim($schedule));
    }

    /**
     * Is it time to run by a cron line; `$now` lets one pass of the worker use the same minute for every job.
     * When both the day of the month and the day of the week are set, either one is enough (as in cron).
     *
     * @param mixed $schedule
     */
    public function isShouldRun($schedule, ?\DateTimeInterface $now = null): bool
    {
        return static::isValid($schedule) && (new CronExpression(trim($schedule)))->isDue($now ?? datetime());
    }

    /**
     * The latest moment by the cron line that is not later than `$now` (the current minute counts)
     */
    public static function lastDue(string $schedule, \DateTimeInterface $now): \DateTimeInterface
    {
        return (new CronExpression(trim($schedule)))->getPreviousRunDate($now, 0, true);
    }

    /**
     * The first moment after `$now`
     */
    public static function nextDue(string $schedule, \DateTimeInterface $now): \DateTimeInterface
    {
        return (new CronExpression(trim($schedule)))->getNextRunDate($now);
    }
}
