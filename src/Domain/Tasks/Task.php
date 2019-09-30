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
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $parametersRepository;

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
        $this->parametersRepository = $this->entityManager->getRepository(\App\Domain\Entities\Parameter::class);

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
        if (!self::$parameters) {
            self::$parameters = collect($this->parametersRepository->findAll());
        }
        if ($key === null) {
            return self::$parameters->mapWithKeys(function ($item) {
                list($group, $key) = explode('_', $item->key, 2);

                return [$group . '[' . $key . ']' => $item];
            });
        }
        if (is_string($key)) {
            return self::$parameters->firstWhere('key', $key)->value ?? $default;
        }

        return self::$parameters->whereIn('key', $key)->pluck('value', 'key')->all() ?? $default;
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
