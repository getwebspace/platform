<?php

// файл для подключения плагинов
// использя ссылку на plugins необходимо зарегистрировать каждый плагин
$plugins = $container->get('plugin');

// тестовый плагин
$plugins->register(new \Plugin\TestPlugin\TestPlugin($container));
