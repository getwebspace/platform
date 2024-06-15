<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Task;
use App\Domain\Service\Task\TaskService;
use App\Domain\Traits\HasParameters;
use App\Domain\Traits\HasRenderer;
use Illuminate\Database\Connection as DataBase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter as Cache;
use Symfony\Component\Cache\Adapter\FilesystemAdapter as FileCache;

abstract class AbstractTask
{
    use HasParameters;
    use HasRenderer;

    public const TITLE = '';

    protected ContainerInterface $container;

    protected LoggerInterface $logger;

    protected DataBase $db;

    protected Cache $cache;

    protected FileCache $persistentCache;

    private ?Task $entity;

    private TaskService $taskService;

    /**
     * Start background worker
     */
    public static function worker(mixed $action = ''): void
    {
        if (is_object($action)) {
            $action = get_class($action);
        }

        @exec('php ' . BIN_DIR . '/task_worker.php ' . addslashes($action) . ' > /dev/null 2>&1 &');
    }

    /**
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
        return file_exists(VAR_DIR . '/' . ($action ? str_replace(['/', '\\'], '-', mb_strtolower($action)) . '.' : '') . 'worker.pid');
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
        $this->cache = $container->get(Cache::class);
        $this->persistentCache = $container->get(FileCache::class);
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
     * @param mixed $output
     *
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setStatusDone(string $output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Casts\Task\Status::DONE, 0, $output);

        return true;
    }

    /**
     * @param mixed $output
     *
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setStatusFail(string $output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Casts\Task\Status::FAIL, 0, $output);

        return false;
    }

    /**
     * @param mixed $output
     *
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setStatusCancel(string $output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Casts\Task\Status::CANCEL, 0, $output);

        return false;
    }

    /**
     * @param mixed $output
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
        ]);

        $this->logger->info('Task: change state', [
            'action' => static::class,
            'status' => $this->entity->status,
            'progress' => $this->entity->progress,
            'output' => $this->entity->output,
        ]);
    }
}
