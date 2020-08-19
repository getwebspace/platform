<?php declare(strict_types=1);

/** @var \Slim\Container $container */

// файл для подключения плагинов
// использя ссылку на plugins необходимо зарегистрировать каждый плагин
$plugins = $container->get('plugin');

// пример подключения плагина
//$plugins->register(new \Plugin\MyPlugin\PluginFile($container));
