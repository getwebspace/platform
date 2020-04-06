<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Domain\Exceptions\HttpBadRequestException;
use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

abstract class Task
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EntityManager
     */
    protected $entityManager;

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
        $this->container = $container;
        $this->logger = $container->get('monolog');
        $this->entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
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

            $this->renderer->getLoader()->addPath(THEME_DIR . '/' . $this->getParameter('common_theme', 'default'));
            $rendered = $this->renderer->fetch($template, $data);

            \RunTracy\Helpers\Profiler\Profiler::finish('render (%s)', $template);

            return $rendered;
        } catch (\Twig\Error\LoaderError $exception) {
            throw new HttpBadRequestException($this->request, $exception->getMessage());
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
            'action' => static::class,
            'params' => $params,
            'status' => \App\Domain\Types\TaskStatusType::STATUS_QUEUE,
            'date' => new \DateTime(),
        ]);

        return $this->entity;
    }

    public function run(): void
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_WORK);
        $this->entityManager->flush();
        $this->logger->info('Task: start', ['action' => static::class]);
        $this->action($this->entity->params);
        $this->entityManager->flush();
        $this->logger->info('Task: done', ['action' => static::class]);
    }

    abstract protected function action(array $args = []);

    public function setStatusDone()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_DONE);

        return true;
    }

    public function setStatusFail()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_FAIL);

        return false;
    }

    public function setStatusCancel()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_CANCEL);

        return false;
    }

    public function setStatusDelete()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_DELETE);

        return false;
    }
}
