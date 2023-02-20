<?php declare(strict_types=1);

use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';

// instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

// set up settings
$settings = require SRC_DIR . '/settings.php';
$settings($containerBuilder);

// set up dependencies
$dependencies = require SRC_DIR . '/dependencies.php';
$dependencies($containerBuilder);

// set up services
$services = require SRC_DIR . '/services.php';
$services($containerBuilder);

// build PHP-DI Container instance
$c = $container = $containerBuilder->build();

// include plugins
require PLUGIN_DIR . '/installed.php';

// instantiate the app
$app = $container->get(\Slim\App::class);
