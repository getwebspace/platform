<?php declare(strict_types=1);

use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder): void {
    $_DEBUG = (bool) ($_ENV['DEBUG'] ?? false);
    $_DATABASE = ($_ENV['DATABASE'] ?? false);

    // database
    $containerBuilder->addDefinitions([
        'database' => [
            'driver' => $_DATABASE ? null : 'sqlite',
            'url' => $_DATABASE,
            'database' => $_DATABASE ?: VAR_DIR . '/database.sqlite',
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'strict'    => true,
            'timezone'  => '+00:00',
        ],
    ]);

    // twig
    $containerBuilder->addDefinitions([
        'twig' => [
            'caches_path' => CACHE_DIR,
        ],
    ]);

    // monolog
    $containerBuilder->addDefinitions([
        'logger' => [
            'name' => 'WSE',
            'path' => LOG_DIR . '/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
    ]);

    // global
    $containerBuilder->addDefinitions([
        'settings' => [
            'displayErrorDetails' => $_DEBUG,
            'logError' => !$_DEBUG,
            'logErrorDetails' => !$_DEBUG,

            // set router cache file if debug is FALSE
            'routerCacheFile' => $_DEBUG ? null : CACHE_DIR . '/routes.cache.php',
        ],
    ]);

    switch ($_DEBUG) {
        case true:
            error_reporting(-1);
            ini_set('display_errors', '1');
            ini_set('html_errors', '1');
            ini_set('error_reporting', '30719');

            break;

        case false:
            // should be enabled in production
            $containerBuilder->enableCompilation(CACHE_DIR);

            break;
    }
};
