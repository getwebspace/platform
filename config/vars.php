<?php

// todo debug
ini_set('display_errors',   '1');
ini_set('html_errors',      '1');
ini_set('error_reporting',  '30719');

// path const
define('BASE_DIR',      realpath(__DIR__ . '/..'));
define('APP_DIR',       realpath(__DIR__ . '/../app'));
define('CONFIG_DIR',    realpath(__DIR__ . '/../config'));
define('PUBLIC_DIR',    realpath(__DIR__ . '/../public'));
define('SRC_DIR',       realpath(__DIR__ . '/../src'));
define('VIEW_DIR',      realpath(__DIR__ . '/../src/Temlate'));
define('THEME_DIR',     realpath(__DIR__ . '/../theme'));
define('VAR_DIR',       realpath(__DIR__ . '/../var'));
define('CACHE_DIR',     realpath(__DIR__ . '/../var/cache'));
define('LOG_DIR',       realpath(__DIR__ . '/../var/log'));
define('UPLOAD_DIR',    realpath(__DIR__ . '/../var/upload'));
define('VENDOR_DIR',    realpath(__DIR__ . '/../vendor'));

require VENDOR_DIR  . '/autoload.php';

/**
 * Hack for get instance from vendor
 *
 * @var \Slim\App $app
 */
$app = app_create();
