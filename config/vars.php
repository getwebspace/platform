<?php

// todo debug
ini_set('display_errors',   '1');
ini_set('html_errors',      '1');
ini_set('error_reporting',  '30719');

// path const
define('BASE_DIR',      realpath(__DIR__ . '/..'));
define('VENDOR_DIR',    realpath(__DIR__ . '/../engine/libs'));
define('SRC_DIR',       realpath(__DIR__ . '/../engine/src'));
define('VAR_DIR',       realpath(__DIR__ . '/../engine/var'));
define('CACHE_DIR',     realpath(__DIR__ . '/../engine/var/cache'));
define('LOG_DIR',       realpath(__DIR__ . '/../engine/var/log'));
define('PUBLIC_DIR',    realpath(__DIR__ . '/../public'));
define('THEME_DIR',     realpath(__DIR__ . '/../theme'));
define('UPLOAD_DIR',    realpath(__DIR__ . '/../uploads'));

require VENDOR_DIR . '/autoload.php';

/**
 * Hack for get instance from vendor
 *
 * @var \Slim\App $app
 */
$app = app_create();
