<?php declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually:
    // for something which should probably be served as a static file
    $url = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }

    // for something served as a dynamic file
    $_SERVER['SCRIPT_NAME'] = '';
}

// Start session
session_start();

// Include global const's
require __DIR__ . '/../src/bootstrap.php';

/**
 * @var \Slim\App $app
 */
RunTracy\Helpers\Profiler\Profiler::start('init middleware');

// Register middleware
require SRC_DIR . '/middleware.php';

RunTracy\Helpers\Profiler\Profiler::finish('init middleware');
RunTracy\Helpers\Profiler\Profiler::start('init routes');

// Register routes
require SRC_DIR . '/routes.php';

RunTracy\Helpers\Profiler\Profiler::finish('init routes');
RunTracy\Helpers\Profiler\Profiler::start('run');

$app->run();

RunTracy\Helpers\Profiler\Profiler::finish('run');

// And nothing more :)
