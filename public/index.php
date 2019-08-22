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

    RunTracy\Helpers\Profiler\Profiler::start('dependencies');

// Set up dependencies
require APP_DIR . '/dependencies.php';

    RunTracy\Helpers\Profiler\Profiler::finish('dependencies');
    RunTracy\Helpers\Profiler\Profiler::start('middleware');

// Register middleware
require APP_DIR . '/middleware.php';

    RunTracy\Helpers\Profiler\Profiler::finish('middleware');
    RunTracy\Helpers\Profiler\Profiler::start('routes');

// Register routes
require APP_DIR . '/routes.php';

    RunTracy\Helpers\Profiler\Profiler::finish('routes');

$app->run();

    RunTracy\Helpers\Profiler\Profiler::finish('app');

// And nothing more :)
