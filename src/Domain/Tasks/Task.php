<?php

namespace App\Domain\Tasks;

use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

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
     * @var \App\Domain\Entities\Task
     */
    private $entity;

    public static $pid_file = VAR_DIR . '/worker.pid';

    /**
     * Запускает исполнение воркера задач
     */
    public static function worker()
    {
        if (!file_exists(static::$pid_file)) {
            exec('php ' . CONFIG_DIR . '/cli-task.php > /dev/null 2>&1 &');
        }
    }

    public function __construct(ContainerInterface $container, \App\Domain\Entities\Task $entity = null)
    {
        $this->container = $container;
        $this->logger = $container->get('monolog');
        $this->entityManager = $container->get(\Doctrine\ORM\EntityManager::class);

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
     * @return array|string|mixed
     */
    protected function getParameter($key = null, $default = null)
    {
        return $this->container->get('parameter')->get($key, $default);
    }

    /**
     * @param array $params
     *
     * @return \App\Domain\Entities\Task
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
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

    public function run()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_WORK);
        $this->entityManager->flush();
        $this->action($this->entity->params);
        $this->entityManager->flush();
    }

    abstract protected function action(array $args = []);

    protected function status_done()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_DONE);
    }

    protected function status_fail()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_FAIL);
    }
}
