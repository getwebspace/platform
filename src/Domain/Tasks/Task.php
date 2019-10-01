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
    protected $entity;

    /**
     * @var array
     */
    protected $params;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $taskRepository;

    /**
     * @var \AEngine\Entity\Collection
     */
    private static $parameters;

    public function __construct(ContainerInterface $container, \App\Domain\Entities\Task $entity = null)
    {
        $this->container = $container;
        $this->logger = $container->get('monolog');
        $this->entityManager = $container->get(\Doctrine\ORM\EntityManager::class);

        $this->taskRepository = $this->entityManager->getRepository(\App\Domain\Entities\Task::class);

        $this->entity = $entity ?? new \App\Domain\Entities\Task();
        $this->params = $this->entity->params;
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
     */
    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        $this->entity->replace([
            'action' => static::class,
            'params' => $params,
            'status' => \App\Domain\Types\TaskStatusType::STATUS_QUEUE,
            'date' => new \DateTime(),
        ]);
        $this->entityManager->persist($this->entity);

        return $this->entity;
    }

    public function run()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_WORK);
        $this->entityManager->flush();
        $this->action();
        $this->entityManager->flush();
    }

    abstract protected function action();

    protected function status_done()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_DONE);
        $this->entityManager->persist($this->entity);
    }

    protected function status_fail()
    {
        $this->entity->set('status', \App\Domain\Types\TaskStatusType::STATUS_FAIL);
        $this->entityManager->persist($this->entity);
    }
}
