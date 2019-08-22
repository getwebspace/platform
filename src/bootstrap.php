<?php

require __DIR__ . '/../config/vars.php';

/**
 * @return \Slim\App
 */
function app_create() {
    // upload dir
    if (!file_exists(PUBLIC_DIR . '/uploads')) {
        symlink(UPLOAD_DIR, PUBLIC_DIR . '/uploads');
    }

    session_start();

    // Get app settings
    $settings = require APP_DIR . '/settings.php';

    $debug = !isset($settings['settings']['displayErrorDetails']) || $settings['settings']['displayErrorDetails'] === true;

    switch ($debug) {
        case true:
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

    // Instantiate and return the app instance
    return new \Slim\App($settings);
}
