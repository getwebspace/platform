<?php

if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

// Include global const's
require __DIR__ . '/../src/bootstrap.php';

    RunTracy\Helpers\Profiler\Profiler::start('app');

// App container
$c = $container = $app->getContainer();

    RunTracy\Helpers\Profiler\Profiler::start('init dependencies');

// Set up dependencies
require SRC_DIR . '/dependencies.php';

    RunTracy\Helpers\Profiler\Profiler::finish('init dependencies');
    RunTracy\Helpers\Profiler\Profiler::start('init middleware');

// Register middleware
require SRC_DIR . '/middleware.php';

    RunTracy\Helpers\Profiler\Profiler::finish('init middleware');
    RunTracy\Helpers\Profiler\Profiler::start('init routes');

// Register routes
require SRC_DIR . '/routes.php';

    RunTracy\Helpers\Profiler\Profiler::finish('init routes');
    RunTracy\Helpers\Profiler\Profiler::start('init plugins');

// Include plugins
require PLUGIN_DIR . '/index.php';

    RunTracy\Helpers\Profiler\Profiler::finish('init plugins');
    RunTracy\Helpers\Profiler\Profiler::start('run');

$app->run();

    RunTracy\Helpers\Profiler\Profiler::finish('run');
    RunTracy\Helpers\Profiler\Profiler::finish('app');

// And nothing more :)
