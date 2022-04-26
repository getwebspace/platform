<?php declare(strict_types=1);

use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder): void {
    $_DEBUG = (bool) ($_ENV['DEBUG'] ?? false);
    $_SALT = (bool) ($_ENV['SALT'] ?? false);
    $_DATABASE = (bool) ($_ENV['DATABASE'] ?? false);
    $_DOCKER = (bool) ($_ENV['DOCKER'] ?? false);

    // secret salt
    $containerBuilder->addDefinitions([
        'secret' => [
            'salt' => ($_SALT ?? 'Li8.1Ej2-<Cid3[bE'),
        ],
    ]);

    // doctrine
    $containerBuilder->addDefinitions([
        'doctrine' => [
            'meta' => [
                'entity_path' => [
                    SRC_DIR . '/Domain/Entities',
                    PLUGIN_DIR,
                ],
                'auto_generate_proxies' => true,
                'proxy_dir' => CACHE_DIR . '/proxies',
                'cache' => null,
            ],

            'types' => require CONFIG_DIR . '/types.php',

            // connection to DB settings
            'connection' => array_merge(
                $_DATABASE ? ['url' => $_DATABASE] : ['driver' => 'pdo_sqlite', 'path' => VAR_DIR . '/database.sqlite'],
                ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'],
            ),
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
            'path' => $_DOCKER ? 'php://stdout' : LOG_DIR . '/app.log',
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
