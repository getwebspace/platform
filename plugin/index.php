<?php

// файл для подключения плагинов
// использя ссылку на plugins необходимо зарегистрировать каждый плагин
$plugins = $container->get('plugin');

// плагин TradeMaster
$plugins->register(new \Plugin\TradeMaster\TradeMasterPlugin($container));
