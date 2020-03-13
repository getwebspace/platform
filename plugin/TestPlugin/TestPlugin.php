<?php

namespace Plugin\TestPlugin;

use App\Application\Plugin;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

class TestPlugin extends Plugin
{
    const TITLE       = "Test Plugin";
    const DESCRIPTION = "Some test functions";
    const AUTHOR      = "Aleksey Ilyin";

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->setRoute('user');
        $this->addParameter([
            'label' => 'Test field',
            'description' => 'Test plugin fields',
            'type' => 'text',
            'name' => 'test',
            'placeholder' => 'Test test test',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Request $request, Response $response): Response
    {
        return $response;
    }
}
