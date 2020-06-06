<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Service\Parameter\ParameterService;
use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractComponent
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ContainerInterface   $container
     * @param EntityManager|null   $entityManager
     * @param LoggerInterface|null $logger
     */
    public function __construct(ContainerInterface $container = null, EntityManager $entityManager = null, LoggerInterface $logger = null)
    {
        if ($container) {
            $this->container = $container;
            $this->entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
            $this->logger = $container->get('monolog');
        } else {
            if ($entityManager) {
                $this->entityManager = $entityManager;
            }

            if ($logger) {
                $this->logger = $logger;
            }
        }
    }

    /**
     * Возвращает значение параметра по переданному ключу
     * Если передан массив ключей, возвращает массив найденных ключей и их значения
     *
     * @param string|string[] $key
     * @param mixed           $default
     *
     * @return array|mixed|string|null
     */
    protected function getParameter($key = null, $default = null)
    {
        if (!empty($this->container)) {
            return $this->container->get('parameter')->get($key, $default);
        }

        throw new \RuntimeException('Container is null');
    }
}
