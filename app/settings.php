<?php

return [
    // secret salt
    'secret' => [
        'salt' => "Li8.1Ej2-<Cid3[bE",
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

        'types' => [
            Ramsey\Uuid\Doctrine\UuidType::NAME => Ramsey\Uuid\Doctrine\UuidType::class,
            Domain\Types\UserLevelType::NAME => \Domain\Types\UserLevelType::class,
            Domain\Types\UserStatusType::NAME => \Domain\Types\UserStatusType::class,
            Domain\Types\FileItemType::NAME => \Domain\Types\FileItemType::class,
            Domain\Types\PageTypeType::NAME => \Domain\Types\PageTypeType::class,
            Domain\Types\GuestBookStatusType::NAME => \Domain\Types\GuestBookStatusType::class,
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
            VIEW_DIR,
            THEME_DIR,
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

    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // allow the web server to send the content-length header
    ],
];
