<?php

use Doctrine\ORM\Tools\Console\ConsoleRunner;

require __DIR__ . '/../config/vars.php';

$settings = $app->getContainer()->get('settings')['doctrine'];

foreach ($settings['types'] as $type => $class) {
    \Doctrine\DBAL\Types\Type::addType($type, $class);
}

$config = \Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration(
    $settings['meta']['entity_path'],
    $settings['meta']['auto_generate_proxies'],
    $settings['meta']['proxy_dir'],
    $settings['meta']['cache'],
    false
);

$em = \Doctrine\ORM\EntityManager::create($settings['connection'], $config);

return ConsoleRunner::createHelperSet($em);
