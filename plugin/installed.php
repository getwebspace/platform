<?php declare(strict_types=1);

/** @var \DI\Container $container */

// file for setup plugins
// by $plugins register your plugin
$plugins = $container->get('plugin');

// Example
// $plugins->register(\Plugin\Example\ExamplePlugin::class);
// $plugins->register(new \Plugin\Example\ExamplePlugin($container));
