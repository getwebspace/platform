<?php declare(strict_types=1);

namespace App\Domain;

use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractService
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var AbstractRepository
     */
    protected $service;

    public function __construct(EntityManager $entityManager, LoggerInterface $logger = null)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public static function getFromContainer(ContainerInterface $container)
    {
        return new static(
            $container->get(\Doctrine\ORM\EntityManager::class),
            $container->get('monolog')
        );
    }

    abstract public function create(array $data = []);

    abstract public function read(array $data = []);

    abstract public function update($entity, array $data = []);

    abstract public function delete($entity);
}
