<?php

// This file can be overridden via volume in docker
return [
    // Secret salt
    'secret' => [
        'salt' => "Li8.1Ej2-<Cid3[bE",
    ],

    // Connection to DB settings
    'doctrine' => [
        'connection' => [
            'driver' => 'pdo_sqlite',
            'path' => VAR_DIR . '/database.sqlite',
        ],
    ],

    // App settings
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
    ],
];
