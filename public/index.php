<?php declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    // to help the built-in PHP dev server, check if the request was actually:
    // for something which should probably be served as a static file
    $url = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }

    // for something served as a dynamic file
    $_SERVER['SCRIPT_NAME'] = '';
}

// start session
session_start();

// Include global const's
require __DIR__ . '/../src/bootstrap.php';

/**
 * @var \Slim\App     $app
 * @var \DI\Container $container
 */
\Netpromotion\Profiler\Profiler::start('init middleware');

// register middleware
$middleware = require SRC_DIR . '/middleware.php';
$middleware($app);

\Netpromotion\Profiler\Profiler::finish('init middleware');
\Netpromotion\Profiler\Profiler::start('init routes');

// register routes
$routes = require SRC_DIR . '/routes.php';
$routes($app, $container);

\Netpromotion\Profiler\Profiler::finish('init routes');
\Netpromotion\Profiler\Profiler::start('run');

$settings = $container->get('settings');
$displayErrorDetails = $settings['displayErrorDetails'];
$logError = $settings['logError'];
$logErrorDetails = $settings['logErrorDetails'];

$app->add(\Slim\Views\TwigMiddleware::createFromContainer($app));
$app->addRoutingMiddleware();
$app->addErrorMiddleware($displayErrorDetails, $logError, $logErrorDetails);
$app->run();

\Netpromotion\Profiler\Profiler::finish('run');

// And nothing more :)
