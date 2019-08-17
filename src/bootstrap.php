<?php

use Doctrine\ORM\EntityManager;

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

    // Set router cache file of display error is negative
    if (!isset($settings['settings']['displayErrorDetails']) || $settings['settings']['displayErrorDetails'] === false) {
        $settings['settings']['routerCacheFile'] = CACHE_DIR . '/routes.cache.php';
    }

    // Instantiate and return the app instance
    return new \Slim\App($settings);
}
