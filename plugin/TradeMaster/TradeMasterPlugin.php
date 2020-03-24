<?php

namespace Plugin\TradeMaster;

use App\Application\Plugin;
use Psr\Container\ContainerInterface;

class TradeMasterPlugin extends Plugin
{
    const NAME         = "TradeMasterPlugin";
    const TITLE        = "TradeMaster";
    const DESCRIPTION  = "Плагин реализует функционал интеграции с системой торгово-складского учета.";
    const AUTHOR       = "Aleksey Ilyin";
    const AUTHOR_SITE  = "https://u4et.ru/trademaster";

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->setTemplateFolder(__DIR__ . '/templates');
        $this->setRoute('catalog');
        $this->addTwigExtension(\Plugin\TradeMaster\TradeMasterPluginTwigExt::class);
        $this->addToolbarItem([
            'twig' => 'trademaster.twig',
        ]);

        $this->addSettingsField([
            'label' => 'Включение и выключение TradeMaster',
            'type' => 'select',
            'name' => 'enable',
            'args' => [
                'option' => [
                    'off' => 'Выключена',
                    'on' => 'Включена',
                ],
            ],
        ]);
        $this->addSettingsField([
            'label' => 'API Host',
            'type' => 'text',
            'name' => 'host',
            'args' => [
                'value' => 'https://api.trademaster.pro',
                'readonly' => true,
            ],
        ]);
        $this->addSettingsField([
            'label' => 'API Version',
            'type' => 'text',
            'name' => 'version',
            'args' => [
                'value' => '2',
                'readonly' => true,
            ],
        ]);
        $this->addSettingsField([
            'label' => 'API Key',
            'description' => 'Введите полученный вами ключ',
            'type' => 'text',
            'name' => 'key',
        ]);
        $this->addSettingsField([
            'label' => 'API Currency',
            'description' => 'Валюта отправляемая по API',
            'type' => 'number',
            'name' => 'currency',
            'args' => [
                'value' => 'RUB',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Cache host',
            'description' => 'Хост кеш файлов',
            'type' => 'text',
            'name' => 'cache_host',
            'args' => [
                'value' => 'https://trademaster.pro',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Cache folder',
            'description' => 'Папка кеш файлов',
            'type' => 'text',
            'name' => 'cache_folder',
        ]);
        $this->addSettingsField([
            'label' => 'Struct',
            'type' => 'number',
            'name' => 'struct',
            'args' => [
                'value' => '0',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Storage',
            'type' => 'number',
            'name' => 'storage',
            'args' => [
                'value' => '0',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Legal',
            'type' => 'number',
            'name' => 'legal',
            'args' => [
                'value' => '0',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Checkout',
            'type' => 'number',
            'name' => 'checkout',
            'args' => [
                'value' => '0',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Contractor',
            'type' => 'number',
            'name' => 'contractor',
            'args' => [
                'value' => '0',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Scheme',
            'type' => 'number',
            'name' => 'scheme',
            'args' => [
                'value' => '0',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'User ID',
            'type' => 'number',
            'name' => 'user',
            'args' => [
                'value' => '0',
            ],
        ]);
    }

    /**
     * @param array $data
     *
     * @return mixed
     */
    public function api(array $data = [])
    {
        $default = [
            'endpoint' => '',
            'params' => [],
            'method' => 'GET',
        ];
        $data = array_merge($default, $data);
        $data['method'] = strtoupper($data['method']);

        if (($key = $this->container->get('parameter')->get('TradeMasterPlugin_key', null)) != null) {
            $pathParts = [$this->container->get('parameter')->get('TradeMasterPlugin_host'), 'v' . $this->container->get('parameter')->get('TradeMasterPlugin_version'), $data['endpoint']];

            if ($data['method'] == "GET") {
                $data['params']['apikey'] = $key;
                $path = implode('/', $pathParts) . '?' . http_build_query($data['params']);

                $result = file_get_contents($path);
            } else {
                $path = implode('/', $pathParts) . '?' . http_build_query(['apikey' => $key]);

                $result = file_get_contents($path, false, stream_context_create([
                    'http' =>
                        [
                            'method' => 'POST',
                            'header' => 'Content-type: application/x-www-form-urlencoded',
                            'content' => http_build_query($data['params']),
                            'timeout' => 60,
                        ],
                ]));
            }

            return json_decode($result, true);
        }

        return [];
    }

    /**
     * Возвращает путь до удаленного файла по имени файла
     *
     * @param string $name
     *
     * @return string
     */
    public function getFilePath(string $name)
    {
        $entities = ['%20', '%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D'];
        $replacements = [' ', '!', '*', "'", "(", ")", ";", ":", "@", "&", "=", "+", "$", ",", "/", "?", "%", "#", "[", "]"];

        $name = str_replace($entities, $replacements, urlencode($name));

        return $this->container->get('parameter')->get('TradeMasterPlugin_cache_host') . '/tradeMasterImages/' . $this->container->get('parameter')->get('TradeMasterPlugin_cache_folder') . '/' . trim($name);
    }
}
