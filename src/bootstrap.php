<?php declare(strict_types=1);

use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/vars.php';

// instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

// set up settings
$settings = require SRC_DIR . '/settings.php';
$settings($containerBuilder);

\Netpromotion\Profiler\Profiler::start('init dependencies');

// set up dependencies
$dependencies = require SRC_DIR . '/dependencies.php';
$dependencies($containerBuilder);

\Netpromotion\Profiler\Profiler::finish('init dependencies');

// build PHP-DI Container instance
$c = $container = $containerBuilder->build();

\Netpromotion\Profiler\Profiler::start('init plugins');

// include plugins
require PLUGIN_DIR . '/installed.php';

\Netpromotion\Profiler\Profiler::finish('init plugins');

// instantiate the app
$app = $container->get(\Slim\App::class);
