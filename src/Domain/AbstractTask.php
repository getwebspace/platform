<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Task;
use App\Domain\Service\Task\TaskService;
use App\Domain\Traits\HasParameters;
use App\Domain\Traits\HasRenderer;
use Illuminate\Cache\ArrayStore as ArrayCache;
use Illuminate\Cache\FileStore as FileCache;
use Illuminate\Database\Connection as DataBase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractTask
{
    use HasParameters;
    use HasRenderer;

    public const TITLE = '';

    protected ContainerInterface $container;

    protected LoggerInterface $logger;

    protected DataBase $db;

    protected ArrayCache $arrayCache;

    protected FileCache $fileCache;

    private ?Task $entity;

    private TaskService $taskService;

    /**
     * Start background worker
     */
    public static function worker(mixed $action = ''): void
    {
        // tests re-create the shared SQLite file before every test case, a worker left
        // running from the previous one holds it and phinx fails with "database is locked";
        // the task itself stays queued, nothing in the tests waits for it to be processed
        if (!empty($_ENV['TEST'])) {
            return;
        }

        if (is_object($action)) {
            $action = get_class($action);
        }

        @exec('php ' . BIN_DIR . '/task_worker.php ' . addslashes($action) . ' > /dev/null 2>&1 &');
    }

    /** @var array<string, resource> locks of the workers of this process */
    private static array $workerLocks = [];

    /**
     * Take the exclusive right to be the worker of the action; false if another process has it.
     * The lock is released by the system when the process ends, however it ends.
     */
    public static function workerLock(string $action = ''): bool
    {
        return self::tryLock($action) === 'got';
    }

    /**
     * @return string got|busy|error
     */
    private static function tryLock(string $action): string
    {
        $path = VAR_DIR . '/' . ($action ? str_replace(['/', '\\'], '-', mb_strtolower($action)) . '.' : '') . 'worker.lock';
        $exists = file_exists($path);

        // 'e': not inherited by the processes started from the worker
        $handle = @fopen($path, 'ce');

        if ($handle === false) {
            error_log("Worker lock {$path} cannot be opened");

            return 'error';
        }

        if (!$exists) {
            @chmod($path, 0o666); // cron (root) and php-fpm (www-data) both start workers
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return 'busy';
        }

        self::$workerLocks[$action] = $handle;

        return 'got';
    }

    public static function workerUnlock(string $action = ''): void
    {
        if (isset(self::$workerLocks[$action])) {
            flock(self::$workerLocks[$action], LOCK_UN);
            fclose(self::$workerLocks[$action]);
            unset(self::$workerLocks[$action]);
        }
    }

    /**
     * Does another process hold the worker of the action right now
     */
    public static function workerIsRunning(string $action = ''): bool
    {
        if (isset(self::$workerLocks[$action])) {
            return false; // it is this process
        }

        $state = self::tryLock($action);

        if ($state === 'got') {
            self::workerUnlock($action);
        }

        return $state === 'busy'; // a file that cannot be opened is not a live worker
    }

    /**
     * Minutes without a sign of life after which a working task is considered stuck
     */
    public const STUCK_AFTER = 10;

    /**
     * Is the working task stuck: its last sign of life (heartbeat, else creation date) is too old
     */
    public static function isStuck(Task $entity, ?\DateTimeInterface $now = null): bool
    {
        $alive = $entity->heartbeat ?? $entity->date;

        return $alive === null || ($now ?? datetime())->getTimestamp() - $alive->getTimestamp() >= self::STUCK_AFTER * 60;
    }

    /**
     * @deprecated the worker is guarded by workerLock()
     *
     * Before start work write self PID to file
     */
    public static function workerCreatePidFile(string $action = ''): void
    {
        file_put_contents(VAR_DIR . '/' . ($action ? str_replace(['/', '\\'], '-', mb_strtolower($action)) . '.' : '') . 'worker.pid', getmypid());
    }

    /**
     * Before start work write self PID to file
     */
    public static function workerHasPidFile(string $action = ''): bool
    {
        $pidFilePath = VAR_DIR . '/' . ($action ? str_replace(['/', '\\'], '-', mb_strtolower($action)) . '.' : '') . 'worker.pid';

        // check pid file exist
        if (!file_exists($pidFilePath)) {
            return false;
        }

        // read pid
        $pid = (int) file_get_contents($pidFilePath);

        // check is process running
        if ($pid > 0 && posix_kill($pid, 0)) {
            return true;
        }

        // remove pid file
        unlink($pidFilePath);

        return false;
    }

    /**
     * After work remove PID file
     */
    public static function workerRemovePidFile(string $action = ''): void
    {
        @unlink(VAR_DIR . '/' . ($action ? str_replace(['/', '\\'], '-', mb_strtolower($action)) . '.' : '') . 'worker.pid');
    }

    /**
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __construct(ContainerInterface $container, ?Task $entity = null)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerInterface::class);
        $this->db = $container->get(DataBase::class);
        $this->arrayCache = $container->get(ArrayCache::class);
        $this->fileCache = $container->get(FileCache::class);
        $this->entity = $entity;
        $this->taskService = $container->get(TaskService::class);
        $this->renderer = $container->get('view');

        // use language
        \App\Application\i18n::$localeCode = $this->parameter('common_language', 'en-US');
    }

    /**
     * @throws \Exception
     */
    public function execute(array $params = []): \App\Domain\Models\Task
    {
        if (!$this->entity) {
            $this->entity = $this->taskService->create([
                'title' => __(static::TITLE),
                'action' => static::class,
                'params' => $params,
                'status' => \App\Domain\Casts\Task\Status::QUEUE,
                'date' => 'now',
            ]);

            return $this->entity;
        }

        throw new \RuntimeException('Exist Task cannot be changed');
    }

    /**
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function run(): void
    {
        $this->setStatusWork();
        $this->container->get(\App\Application\PubSub::class)->publish(static::class . ':start', $this->entity);
        $this->action($this->entity->params);
        $this->container->get(\App\Application\PubSub::class)->publish(static::class . ':finish', $this->entity);
        $this->logger->info('Task: done', ['action' => static::class]);
    }

    abstract protected function action(array $args = []);

    /**
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setProgress(float|int $value, float|int $count = 0): void
    {
        if ($count > 0) {
            $value = round(min($value, $count) / $count * 100);
        }
        if ($value !== $this->entity->progress) {
            $this->saveStateWriteLog(\App\Domain\Casts\Task\Status::WORK, (int) $value);
        } elseif (time() - ($this->entity->heartbeat?->getTimestamp() ?? 0) >= 30) {
            // the progress did not move but the task is alive
            $this->entity = $this->taskService->update($this->entity, ['heartbeat' => 'now']);
        }
    }

    /**
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setStatusWork(): bool
    {
        $this->saveStateWriteLog(\App\Domain\Casts\Task\Status::WORK);

        return true;
    }

    /**
     * @param string $output
     *
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setStatusDone(string $output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Casts\Task\Status::DONE, 0, $output);

        return true;
    }

    /**
     * @param string $output
     *
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setStatusFail(string $output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Casts\Task\Status::FAIL, 0, $output);

        return false;
    }

    /**
     * @param string $output
     *
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setStatusCancel(string $output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Casts\Task\Status::CANCEL, 0, $output);

        return false;
    }

    /**
     * @param string $output
     *
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setStatusDelete(string $output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Casts\Task\Status::DELETE, 0, $output);

        return false;
    }

    public function getStatus(): string
    {
        return $this->entity->status;
    }

    /**
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    private function saveStateWriteLog(?string $status = null, int $progress = 0, string $output = ''): void
    {
        $this->entity = $this->taskService->update($this->entity, [
            'status' => $status,
            'progress' => $progress,
            'output' => $output,
            'heartbeat' => 'now',
        ]);

        $this->logger->info('Task: change state', [
            'action' => static::class,
            'status' => $this->entity->status,
            'progress' => $this->entity->progress,
            'output' => $this->entity->output,
        ]);
    }
}
