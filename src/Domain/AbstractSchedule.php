<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Service\Task\TaskService;
use App\Domain\Traits\HasParameters;
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

    public function isShouldRun($schedule): bool
    {
        $currentTime = datetime();
        $currentMinute = (int) $currentTime->format('i');
        $currentHour = (int) $currentTime->format('H');
        $currentDay = (int) $currentTime->format('j');
        $currentMonth = (int) $currentTime->format('n');
        $currentDayOfWeek = (int) $currentTime->format('N');

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = explode(' ', $schedule);

        return $this->matches($minute, $currentMinute)
            && $this->matches($hour, $currentHour)
            && $this->matches($dayOfMonth, $currentDay)
            && $this->matches($month, $currentMonth)
            && $this->matches($dayOfWeek, $currentDayOfWeek);
    }

    protected function matches($cronPart, $currentPart): bool
    {
        if ($cronPart === '*') {
            return true;
        }

        $parts = explode(',', $cronPart);

        foreach ($parts as $part) {
            if (str_contains($part, '-')) {
                [$start, $end] = explode('-', $part);

                if ($currentPart >= $start && $currentPart <= $end) {
                    return true;
                }
            } elseif (str_contains($part, '/')) {
                [$base, $interval] = explode('/', $part);

                if ($base === '*') {
                    $base = 0;
                }
                if (($currentPart - $base) % $interval === 0) {
                    return true;
                }
            } else {
                if ((int) $part === $currentPart) {
                    return true;
                }
            }
        }

        return false;
    }
}
