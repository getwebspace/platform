<?php declare(strict_types=1);

use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder): void {
    $_DEBUG = (bool) ($_ENV['DEBUG'] ?? false);
    $_CACHE_DIR = CACHE_DIR . '/' . mb_substr($_ENV['COMMIT_SHA'] ?? 'specific', 0, 7);

    // check if cache folder exists
    if (!is_dir($_CACHE_DIR)) {
        if (@mkdir($_CACHE_DIR) === false) {
            $_CACHE_DIR = CACHE_DIR;
        }
    }

    // secret salt
    $containerBuilder->addDefinitions([
        'secret' => [
            'salt' => ($_ENV['SALT'] ?? 'Li8.1Ej2-<Cid3[bE'),
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
                'proxy_dir' => $_CACHE_DIR . '/proxies',
                'cache' => null,
            ],

            'types' => require CONFIG_DIR . '/types.php',

            // connection to DB settings
            'connection' => !empty($_ENV['DATABASE']) ? ['url' => $_ENV['DATABASE']] : [
                'driver' => 'pdo_sqlite',
                'path' => VAR_DIR . '/database.sqlite',
            ],
        ],
    ]);

    // twig
    $containerBuilder->addDefinitions([
        'twig' => [
            'caches_path' => $_CACHE_DIR,
        ],
    ]);

    // monolog
    $containerBuilder->addDefinitions([
        'logger' => [
            'name' => 'WSE',
            'path' => isset($_ENV['DOCKER']) ? 'php://stdout' : LOG_DIR . '/app.log',
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
            'routerCacheFile' => $_DEBUG ? null : $_CACHE_DIR . '/routes.cache.php',
        ],
    ]);

    switch ($_DEBUG) {
        case true:
            error_reporting(-1);
            ini_set('display_errors', '1');
            ini_set('html_errors', '1');
            ini_set('error_reporting', '30719');

            // enable Tracy panel
            \Tracy\Debugger::$strictMode = E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED;
            \Tracy\Debugger::enable(\Tracy\Debugger::DEVELOPMENT, LOG_DIR);

            // enable Profiler
//            \Netpromotion\Profiler\Profiler::enable();
//            \Tracy\Debugger::getBar()->addPanel(new \Netpromotion\Profiler\Adapter\TracyBarAdapter([
//                'primaryValue' => 'effective', // or 'absolute'
//                'show' => [
//                    'memoryUsageChart' => false,
//                    'shortProfiles' => true,
//                    'timeLines' => false,
//                ],
//            ]));

            break;

        case false:
            // should be enabled in production
            $containerBuilder->enableCompilation($_CACHE_DIR);

            // enable Tracy panel
            \Tracy\Debugger::enable(\Tracy\Debugger::PRODUCTION, LOG_DIR);

            break;
    }
};
