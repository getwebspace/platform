<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Entities\Task;
use App\Domain\Service\Task\TaskService;
use App\Domain\Traits\ParameterTrait;
use App\Domain\Traits\RendererTrait;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractTask
{
    use ParameterTrait;
    use RendererTrait;

    public const TITLE = '';

    protected ContainerInterface $container;

    protected LoggerInterface $logger;

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
    public function __construct(ContainerInterface $container, Task $entity = null)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerInterface::class);
        $this->entity = $entity;
        $this->taskService = $container->get(TaskService::class);
        $this->renderer = $container->get('view');
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        if (!$this->entity) {
            $this->entity = $this->taskService->create([
                'title' => __(static::TITLE),
                'action' => static::class,
                'params' => $params,
                'status' => \App\Domain\Types\TaskStatusType::STATUS_QUEUE,
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
        $this->action($this->entity->getParams());
        $this->container->get(\App\Application\PubSub::class)->publish(static::class . ':finish', $this->entity);
        $this->logger->info('Task: done', ['action' => static::class]);
    }

    abstract protected function action(array $args = []);

    /**
     * @param mixed $value
     * @param mixed $count
     *
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setProgress($value, $count = 0): void
    {
        if ($count > 0) {
            $value = round(min($value, $count) / $count * 100);
        }
        if ($value !== $this->entity->getProgress()) {
            $this->saveStateWriteLog(\App\Domain\Types\TaskStatusType::STATUS_WORK, (int) $value);
        }
    }

    /**
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setStatusWork(): bool
    {
        $this->saveStateWriteLog(\App\Domain\Types\TaskStatusType::STATUS_WORK);

        return true;
    }

    /**
     * @param mixed $output
     *
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setStatusDone($output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Types\TaskStatusType::STATUS_DONE, 0, $output);

        return true;
    }

    /**
     * @param mixed $output
     *
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setStatusFail($output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Types\TaskStatusType::STATUS_FAIL, 0, $output);

        return false;
    }

    /**
     * @param mixed $output
     *
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setStatusCancel($output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Types\TaskStatusType::STATUS_CANCEL, 0, $output);

        return false;
    }

    /**
     * @param mixed $output
     *
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    public function setStatusDelete($output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Types\TaskStatusType::STATUS_DELETE, 0, $output);

        return false;
    }

    public function getStatus(): string
    {
        return $this->entity->getStatus();
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
            'status' => $this->entity->getStatus(),
            'progress' => $this->entity->getProgress(),
            'output' => $this->entity->getOutput(),
        ]);
    }
}
