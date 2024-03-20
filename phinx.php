<?php

return [
    'paths' => [
        'migrations' => 'scheme/migrations',
        'seeds' => 'scheme/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
    ],
    'version_order' => 'creation'
];
