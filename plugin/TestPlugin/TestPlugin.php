<?php

namespace Plugin\TestPlugin;

use App\Application\Plugin;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

class TestPlugin extends Plugin
{
    const NAME          = "TestPlugin";
    const TITLE         = "Тестовый плагин";
    const DESCRIPTION   = "Тут какой-то тестовый функционал";
    const AUTHOR        = "Aleksey Ilyin";
    const AUTHOR_EMAIL  = "alksily@outlook.com";
    const AUTHOR_SITE  = "https://site.0x12f.com";

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->setRoute('user', 'main');

        for ($i = 1; $i < 5; $i++) {
            $this->addParameter([
                'label' => 'Тестовое поле ' . $i,
                'description' => 'Какое-то описание ' . $i,
                'type' => 'text',
                'name' => 'test' . $i,
                'args' => [
                    'placeholder' => 'Ла-ла-ла!',
                ],
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    public function execute(Request $request, Response $response): Response
    {
        return $response;
    }
}
