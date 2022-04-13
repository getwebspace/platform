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
// register middleware
$middleware = require SRC_DIR . '/middleware.php';
$middleware($app);

// register routes
$routes = require SRC_DIR . '/routes.php';
$routes($app, $container);

$settings = $container->get('settings');
$displayErrorDetails = $settings['displayErrorDetails'];
$logError = $settings['logError'];
$logErrorDetails = $settings['logErrorDetails'];
$logger = $container->get(\Psr\Log\LoggerInterface::class);

$app->add(\Slim\Views\TwigMiddleware::createFromContainer($app));
$app->addRoutingMiddleware();
$app->addErrorMiddleware($displayErrorDetails, $logError, $logErrorDetails, $logger);
$app->run();

// And nothing more :)
