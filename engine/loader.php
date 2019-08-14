<?php

use Doctrine\ORM\EntityManager;

/**
 * @return \Slim\App
 */
function app_create() {
    // asset dir
    if (!file_exists(PUBLIC_DIR . '/assets')) {
        symlink(VENDOR_DIR . '/0x12f/cms-engine/assets', PUBLIC_DIR . '/assets');

        // todo remove
        symlink(VENDOR_DIR . '/0x12f/cms-engine/documentation', PUBLIC_DIR . '/documentation');
        symlink(VENDOR_DIR . '/0x12f/cms-engine/examples', PUBLIC_DIR . '/examples');
    }

    // upload dir
    if (!file_exists(PUBLIC_DIR . '/uploads')) {
        symlink(UPLOAD_DIR, PUBLIC_DIR . '/uploads');
    }

    session_start();

    // Get app settings
    $settings = require __DIR__ . '/settings.php';

    // Set router cache file of display error is negative
    if (!isset($settings['settings']['displayErrorDetails']) || $settings['settings']['displayErrorDetails'] === false) {
        $settings['settings']['routerCacheFile'] = CACHE_DIR . '/routes.cache.php';
    }

    // Instantiate and return the app instance
    return new \Slim\App($settings);
}

/**
 * @param \Slim\App $app
 *
 * @return \Psr\Http\Message\ResponseInterface
 * @throws \Slim\Exception\MethodNotAllowedException
 * @throws \Slim\Exception\NotFoundException
 */
function app_run(\Slim\App $app) {
    // App container
    $c = $container = $app->getContainer();

    // Set up dependencies
    require __DIR__ . '/dependencies.php';

    // Register middleware
    require __DIR__ . '/middleware.php';

    // Register routes
    require __DIR__ . '/routes.php';

    return $app->run();
}
