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

// App container
$c = $container = $app->getContainer();

// Set up dependencies
require APP_DIR . '/dependencies.php';

// Register middleware
require APP_DIR . '/middleware.php';

// Register routes
require APP_DIR . '/routes.php';

return $app->run();

// And nothing more :)
