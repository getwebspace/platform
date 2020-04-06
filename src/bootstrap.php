<?php declare(strict_types=1);

require __DIR__ . '/../config/vars.php';

/**
 * @return \Slim\App
 */
function app_create()
{
    session_start();

    // Get app settings
    $settings = require SRC_DIR . '/settings.php';

    switch (!isset($settings['settings']['displayErrorDetails']) || $settings['settings']['displayErrorDetails'] === true) {
        case true:
            error_reporting(-1);
            ini_set('display_errors',   '1');
            ini_set('html_errors',      '1');
            ini_set('error_reporting',  '30719');

            // enable Tracy panel
            \Tracy\Debugger::enable(\Tracy\Debugger::DEVELOPMENT, LOG_DIR);

            // enably Profiler
            RunTracy\Helpers\Profiler\Profiler::enable();

            break;

        case false:
            // Set router cache file if display error is negative
            $settings['settings']['routerCacheFile'] = CACHE_DIR . '/routes.cache.php';

            // enable Tracy panel
            \Tracy\Debugger::enable(\Tracy\Debugger::PRODUCTION, LOG_DIR);

            break;
    }

    if (isset($settings['settings']['sentry']) && $settings['settings']['sentry'] !== null) {
        \RunTracy\Helpers\Profiler\Profiler::start('sentry');
        \Sentry\init(['dsn' => $settings['settings']['sentry']]);
        \RunTracy\Helpers\Profiler\Profiler::finish('sentry');
    }

    // Instantiate and return the app instance
    return new \Slim\App($settings);
}

/**
 * Hack for get instance from vendor
 *
 * @var \Slim\App
 */
$app = app_create();

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
RunTracy\Helpers\Profiler\Profiler::start('init plugins');

// Include plugins
require PLUGIN_DIR . '/index.php';

RunTracy\Helpers\Profiler\Profiler::finish('init plugins');
RunTracy\Helpers\Profiler\Profiler::start('init routes');

// Register routes
require SRC_DIR . '/routes.php';

RunTracy\Helpers\Profiler\Profiler::finish('init routes');
