<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Service\Parameter\ParameterService;
use Doctrine\ORM\EntityManager;
use Illuminate\Support\Collection;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractComponent
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @var EntityManager
     */
    protected EntityManager $entityManager;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @param null|ContainerInterface $container
     * @param null|EntityManager      $entityManager
     * @param null|LoggerInterface    $logger
     */
    public function __construct(ContainerInterface $container = null, EntityManager $entityManager = null, LoggerInterface $logger = null)
    {
        if ($container) {
            $this->container = $container;
            $this->entityManager = $container->get(EntityManager::class);
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
     * @param null|string|string[] $key
     * @param mixed                $default
     *
     * @return null|array|Collection|string
     */
    protected function parameter($key = null, $default = null)
    {
        static $parameters;

        if (!$parameters) {
            if ($this->container) {
                \RunTracy\Helpers\Profiler\Profiler::start('parameters');
                $parameters = ParameterService::getWithContainer($this->container)->read();
                \RunTracy\Helpers\Profiler\Profiler::finish('parameters');
            } else {
                throw new \RuntimeException('Container is null');
            }
        }

        if ($parameters) {
            if ($key === null) {
                return $parameters->mapWithKeys(function ($item) {
                    [$group, $key] = explode('_', $item->key, 2);

                    return [$group . '[' . $key . ']' => $item];
                });
            }
            if (is_string($key)) {
                return ($buf = $parameters->firstWhere('key', $key)) ? $buf->getValue() : $default;
            }

            return $parameters->whereIn('key', $key)->pluck('value', 'key')->all() ?? $default;
        }

        return $default;
    }
}
