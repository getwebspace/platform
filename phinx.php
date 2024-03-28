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
            'name' => 'dev',
            'connection' => new PDO('sqlite:./var/database-test.sqlite')
        ],
        'prod' => [
            'name' => 'prod',
            'connection' => new PDO($_ENV['DATABASE'] ?? 'sqlite:./var/database.sqlite'),
        ]
    ],
    'version_order' => 'creation'
];
