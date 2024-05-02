<?php
declare(strict_types=1);

return [
    'paths' => [
        'migrations' => 'scheme/migrations',
        'seeds' => 'scheme/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinx_migrations',
        'default_environment' => ($_ENV['TEST'] ?? false) ? 'dev' : 'prod',
        'dev' => [
            'dsn' => 'sqlite://./var/database-test',
            'suffix' =>  '.sqlite'
        ],
        'prod' => [
            'dsn' => $_ENV['DATABASE'] ?: 'sqlite://./var/database',
            'suffix' =>  '.sqlite'
        ]
    ],
    'version_order' => 'creation'
];
