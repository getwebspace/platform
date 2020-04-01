<?php

// path const
define('BASE_DIR',      realpath(__DIR__ . '/..'));
define('CONFIG_DIR',    realpath(__DIR__ . '/../config'));
define('PLUGIN_DIR',    realpath(__DIR__ . '/../plugin'));
define('PUBLIC_DIR',    realpath(__DIR__ . '/../public'));
define('UPLOAD_DIR',    realpath(__DIR__ . '/../public/uploads'));
define('SRC_DIR',       realpath(__DIR__ . '/../src'));
define('VIEW_DIR',      realpath(__DIR__ . '/../src/Template'));
define('THEME_DIR',     realpath(__DIR__ . '/../theme'));
define('VAR_DIR',       realpath(__DIR__ . '/../var'));
define('XML_DIR',       realpath(__DIR__ . '/../var/xml'));
define('CACHE_DIR',     realpath(__DIR__ . '/../var/cache'));
define('LOG_DIR',       realpath(__DIR__ . '/../var/log'));
define('VENDOR_DIR',    realpath(__DIR__ . '/../vendor'));

require VENDOR_DIR  . '/autoload.php';
