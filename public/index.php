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

    RunTracy\Helpers\Profiler\Profiler::start('run');

$app->run();

    RunTracy\Helpers\Profiler\Profiler::finish('run');

// And nothing more :)
