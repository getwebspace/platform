<?php declare(strict_types=1);

use Doctrine\ORM\Tools\Console\ConsoleRunner;

require __DIR__ . '/../src/bootstrap.php';

$settings = $app->getContainer()->get('doctrine');

$config = \Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration(
    $settings['meta']['entity_path'],
    $settings['meta']['auto_generate_proxies'],
    $settings['meta']['proxy_dir'],
    $settings['meta']['cache'],
    false
);

$em = \Doctrine\ORM\EntityManager::create($settings['connection'], $config);
$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

return ConsoleRunner::createHelperSet($em);
