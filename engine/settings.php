<?php

return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // allow the web server to send the content-length header

        // Doctrine settings
        'doctrine' => [
            'meta' => [
                'entity_path' => [
                    APP_DIR . '/Entity',
                ],
                'auto_generate_proxies' => true,
                'proxy_dir' => CACHE_DIR . '/proxies',
                'cache' => null,
            ],

            'types' => [
                Ramsey\Uuid\Doctrine\UuidType::NAME => Ramsey\Uuid\Doctrine\UuidType::class,
            ],

            // Connection to DB settings
//            'connection' => [
//                'driver' => 'pdo_sqlite',
//                'path' => VAR_DIR . '/database.sqlite',
//            ],

            // Connection to Dev DB TODO remove this!
            'connection' => [
                'driver' => 'pdo_mysql',
                'dbname' => 'cms',
                'user' => 'root',
                'password' => '123100',
                'host' => 'localhost',
            ]
        ],

        // Render settings
        'renderer' => [
            'template_path' => [
                THEME_DIR,
                TEMPLATE_DIR,
            ],
        ],

        // Twig settings
        'twig' => [
            'caches_path' => CACHE_DIR,
        ],

        // Monolog settings
        'logger' => [
            'name' => '0x12f',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : LOG_DIR . '/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
    ],
];
