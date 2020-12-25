<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Entities\Task;
use App\Domain\Exceptions\HttpBadRequestException;
use App\Domain\Service\Task\TaskService;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Slim\Views\Twig;

abstract class AbstractTask extends AbstractComponent
{
    public const TITLE = '';

    /**
     * @var TaskService
     */
    private TaskService $taskService;

    /**
     * @var null|Task
     */
    private ?Task $entity;

    /**
     * @var Twig
     */
    private Twig $renderer;

    public static string $pid_file = VAR_DIR . '/worker.pid';

    /**
     * Запускает исполнение воркера задач
     */
    public static function worker(): void
    {
        if (!file_exists(static::$pid_file)) {
            @exec('php ' . CONFIG_DIR . '/cli-task.php > /dev/null 2>&1 &');
        }
    }

    public function __construct(ContainerInterface $container, Task $entity = null)
    {
        parent::__construct($container);

        /** @var TaskService $taskService */
        $this->taskService = TaskService::getWithContainer($container);
        $this->entity = $entity;
        $this->renderer = $container->get('view');
    }

    /**
     * @param string $template
     * @param array  $data
     *
     * @throws HttpBadRequestException
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     *
     * @return string
     */
    protected function render(string $template, array $data = []): string
    {
        try {
            \RunTracy\Helpers\Profiler\Profiler::start('render (%s)', $template);

            if (($path = realpath(THEME_DIR . '/' . $this->parameter('common_theme', 'default'))) !== false) {
                $this->renderer->getLoader()->addPath($path);
            }
            $rendered = $this->renderer->fetch($template, $data);

            \RunTracy\Helpers\Profiler\Profiler::finish('render (%s)', $template);

            return $rendered;
        } catch (\Twig\Error\LoaderError $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    /**
     * @param array $params
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     *
     * @return \App\Domain\Entities\Task
     */
    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        if (!$this->entity) {
            $this->entity = $this->taskService->create([
                'title' => static::TITLE,
                'action' => static::class,
                'params' => $params,
                'status' => \App\Domain\Types\TaskStatusType::STATUS_QUEUE,
                'date' => 'now',
            ]);

            return $this->entity;
        }

        throw new RuntimeException('Exist Task cannot be changed');
    }

    public function run(): void
    {
        $this->setStatusWork();
        $this->action($this->entity->getParams());
        $this->logger->info('Task: done', ['action' => static::class]);
    }

    abstract protected function action(array $args = []);

    public function setProgress($value, $count = 0): void
    {
        if ($count !== 0) {
            $value = round(min($value, $count) / $count * 100);
        }
        if ($value !== $this->entity->getProgress()) {
            $this->saveStateWriteLog(\App\Domain\Types\TaskStatusType::STATUS_WORK, $value);
        }
    }

    public function setStatusWork(): bool
    {
        $this->saveStateWriteLog(\App\Domain\Types\TaskStatusType::STATUS_WORK);

        return true;
    }

    public function setStatusDone($output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Types\TaskStatusType::STATUS_DONE, 0, $output);

        return true;
    }

    public function setStatusFail($output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Types\TaskStatusType::STATUS_FAIL, 0, $output);

        return false;
    }

    public function setStatusCancel($output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Types\TaskStatusType::STATUS_CANCEL, 0, $output);

        return false;
    }

    public function setStatusDelete($output = ''): bool
    {
        $this->saveStateWriteLog(\App\Domain\Types\TaskStatusType::STATUS_DELETE, 0, $output);

        return false;
    }

    /**
     * @param null|string $status
     * @param int         $progress
     * @param string      $output
     *
     * @throws Service\Task\Exception\TaskNotFoundException
     */
    private function saveStateWriteLog($status = null, $progress = 0, $output = ''): void
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
