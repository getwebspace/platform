<?php declare(strict_types=1);

// timezone default
date_default_timezone_set('UTC');

return [
    // Secret salt
    'secret' => [
        'salt' => ($_ENV['SALT'] ?? 'Li8.1Ej2-<Cid3[bE'),
    ],

    // Doctrine settings
    'doctrine' => [
        'meta' => [
            'entity_path' => [
                SRC_DIR . '/Domain/Entities',
            ],
            'auto_generate_proxies' => true,
            'proxy_dir' => CACHE_DIR . '/proxies',
            'cache' => null,
        ],

        'types' => require CONFIG_DIR . '/types.php',

        // Connection to DB settings
        'connection' => $_ENV['DATABASE'] ?? [
            'driver' => 'pdo_sqlite',
            'path' => VAR_DIR . '/database.sqlite',
        ],
    ],

    // Twig settings
    'twig' => [
        'caches_path' => CACHE_DIR,
    ],

    // Monolog settings
    'logger' => [
        'name' => 'WSE',
        'path' => isset($_ENV['docker']) ? 'php://stdout' : LOG_DIR . '/app.log',
        'level' => \Monolog\Logger::DEBUG,
    ],

    'settings' => [
        'sentry' => ($_ENV['SENTRY'] ?? null),
        'displayErrorDetails' => ((bool) $_ENV['DEBUG'] ?? false), // set to false in production
        'addContentLengthHeader' => false, // allow the web server to send the content-length header
        'determineRouteBeforeAppMiddleware' => true,

        'tracy' => [
            'showPhpInfoPanel' => 0,
            'showSlimRouterPanel' => 0,
            'showSlimEnvironmentPanel' => 0,
            'showSlimRequestPanel' => 0,
            'showSlimResponsePanel' => 0,
            'showSlimContainer' => 0,
            'showTwigPanel' => 1,
            'showDoctrinePanel' => \Doctrine\ORM\EntityManager::class,
            'showProfilerPanel' => 1,
            'showVendorVersionsPanel' => 0,
            'showIncludedFiles' => 0,
            'configs' => [
                'ConsoleNoLogin' => 0,
                'ProfilerPanel' => [
                    'primaryValue' => 'effective', // or 'absolute'
                    'show' => [
                        'memoryUsageChart' => false,
                        'shortProfiles' => true,
                        'timeLines' => false,
                    ],
                ],
            ],
        ],
    ],
];
