<?php

namespace App\Application;

use Psr\Container\ContainerInterface;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;

abstract class Plugin
{
    const TITLE       = "";
    const DESCRIPTION = "";
    const AUTHOR      = "";
    const VERSION     = 1.0;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $routes = [];

    /**
     * @var array
     */
    protected $parameters = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        pre();

        if (empty(static::TITLE) || empty(static::DESCRIPTION) || empty(static::AUTHOR)) {
            throw new RuntimeException('One of plugin credentials is empty');
        }
    }

    public function getCredentials()
    {
        return [
            'title' => static::TITLE,
            'description' => static::DESCRIPTION,
            'author' => static::AUTHOR,
            'version' => static::VERSION,
        ];
    }

    protected function setRoute(...$name)
    {
        $this->routes = array_merge($this->routes, $name);
    }

    public function getRoute()
    {
        return array_unique($this->routes);
    }

    protected function addParameter($params = [])
    {
        $default = [
            'label' => '',
            'description' => '',
            'type' => 'text',
            'name' => '',
            'value' => null,
            'placeholder' => '',
            'options' => [],
            'selected' => null,
            'checked' => null,
        ];
        $params = array_merge($default, $params);
        $params['name'] = lcfirst($this->getClassName() . '[' . $params['name'] . ']');

        $this->parameters[$params['name']] = $params;
    }

    public function getParameters()
    {
        return array_unique($this->parameters);
    }

    private function getClassName()
    {
        return substr(get_class($this), strrpos(get_class($this), '\\') + 1);
    }

    /**
     * Функция выполнится ДО обработки выбранной группы роутов
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return Response
     */
    abstract public function execute(Request $request, Response $response): Response;
}
