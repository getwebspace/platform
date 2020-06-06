<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exceptions\HttpBadRequestException;
use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

abstract class AbstractTask extends AbstractComponent
{
    public const TITLE = '';

    /**
     * @var Twig
     */
    protected $renderer;

    /**
     * @var \App\Domain\Entities\Task
     */
    private $entity;

    public static $pid_file = VAR_DIR . '/worker.pid';

    /**
     * Запускает исполнение воркера задач
     */
    public static function worker(): void
    {
        if (!file_exists(static::$pid_file)) {
            @exec('php ' . CONFIG_DIR . '/cli-task.php > /dev/null 2>&1 &');
        }
    }

    public function __construct(ContainerInterface $container, \App\Domain\Entities\Task $entity = null)
    {
        parent::__construct($container);

        $this->renderer = $container->get('view');

        if (!$entity) {
            $entity = new \App\Domain\Entities\Task();
            $this->entityManager->persist($entity);
        }
        $this->entity = $entity;
    }

    /**
     * Возвращает значение параметра по переданному ключу
     * Если передан массив ключей, возвращает массив найденных ключей и их значения
     *
     * @param string|string[] $key
     * @param mixed           $default
     *
     * @return array|mixed|string
     */
    protected function getParameter($key = null, $default = null)
    {
        return $this->container->get('parameter')->get($key, $default);
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
    protected function render($template, array $data = [])
    {
        try {
            \RunTracy\Helpers\Profiler\Profiler::start('render (%s)', $template);

            if (($path = realpath(THEME_DIR . '/' . $this->getParameter('common_theme', 'default'))) !== false) {
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
        $this->entity->replace([
            'title' => static::TITLE,
            'action' => static::class,
            'params' => $params,
            'status' => \App\Domain\Types\TaskStatusType::STATUS_QUEUE,
            'date' => new \DateTime(),
        ]);

        return $this->entity;
    }

    public function run(): void
    {
        $this->setStatusWork();
        $this->action($this->entity->params);
        $this->logger->info('Task: done', ['action' => static::class]);
    }

    abstract protected function action(array $args = []);

    public function setProgress($value, $count = 0): void
    {
        if ($count !== 0) {
            $value = round(min($value, $count) / $count * 100);
        }
        if ($value !== $this->entity->progress) {
            $this->entity->progress = $value;

            switch ($this->entity->progress) {
                case 100:
                    sleep(1);

                    break;

                default:
                    $this->saveStateLogPush();

                    break;
            }
        }
    }

    public function setStatusWork()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_WORK);
        $this->saveStateLogPush();

        return true;
    }

    public function setStatusDone()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_DONE);
        $this->saveStateLogPush();

        return true;
    }

    public function setStatusFail()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_FAIL);
        $this->saveStateLogPush();

        return false;
    }

    public function setStatusCancel()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_CANCEL);
        $this->saveStateLogPush();

        return false;
    }

    public function setStatusDelete()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_DELETE);
        $this->saveStateLogPush();

        return false;
    }

    private function saveStateLogPush(): void
    {
        $this->entityManager->flush();

        // отправляем пуш
        $this->container->get('pushstream')->send([
            'group' => \App\Domain\Types\UserLevelType::LEVEL_ADMIN,
            'content' => [
                'type' => 'task',
                'uuid' => $this->entity->uuid->toString(),
                'title' => $this->entity->getTitle(),
                'status' => $this->entity->status,
                'progress' => $this->entity->progress,
            ],
        ]);

        $this->logger->info('Task: change state', [
            'action' => static::class,
            'status' => $this->entity->status,
            'progress' => $this->entity->progress,
        ]);
    }
}
