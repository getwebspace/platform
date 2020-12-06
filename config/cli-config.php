<?php declare(strict_types=1);

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Psr\Container\ContainerInterface;

/**
 * @global ContainerInterface $container
 */
require __DIR__ . '/../src/bootstrap.php';

return ConsoleRunner::createHelperSet(
    $container->get(\Doctrine\ORM\EntityManager::class)
);
