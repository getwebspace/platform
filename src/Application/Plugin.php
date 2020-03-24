<?php

namespace App\Application;

use App\Domain\Exceptions\HttpBadRequestException;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

abstract class Plugin
{
    const NAME          = "";
    const TITLE         = "";
    const DESCRIPTION   = "";
    const AUTHOR        = "";
    const AUTHOR_EMAIL  = "";
    const AUTHOR_SITE   = "";
    const VERSION       = "1.0";

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var Twig
     */
    protected $renderer;

    /**
     * @var string
     */
    protected $templateFolder = null;

    /**
     * @var array
     */
    protected $routes = [];

    /**
     * @var array
     */
    protected $settingsField = [];

    /**
     * @var array
     */
    protected $toolbars = [];

    public function __construct(ContainerInterface $container)
    {
        if (empty(static::NAME) || empty(static::TITLE) || empty(static::AUTHOR)) {
            throw new RuntimeException('Plugin credentials have empty fields');
        }

        $this->container = $container;
        $this->container[static::NAME] = $this;
        $this->renderer = $container->get('view');
    }

    public function getCredentials($field = null)
    {
        $credentials = [
            'title' => static::TITLE,
            'description' => static::DESCRIPTION,
            'author' => static::AUTHOR,
            'author_email' => static::AUTHOR_EMAIL,
            'author_site' => static::AUTHOR_SITE,
            'name' => static::NAME,
            'version' => static::VERSION,
        ];

        if (in_array($field, array_keys($credentials))) {
            return $credentials[$field];
        }

        return $credentials;
    }

    protected function setTemplateFolder($path)
    {
        $this->renderer->getLoader()->addPath($path);
    }

    public function getTemplateFolder()
    {
        return $this->templateFolder;
    }

    protected function setRoute(...$name)
    {
        $this->routes = array_merge($this->routes, $name);
    }

    public function getRoute()
    {
        return $this->routes;
    }

    protected function addTwigExtension($extension)
    {
        $this->renderer->addExtension(new $extension($this->container, $this));
    }

    protected function addSettingsField($params = [])
    {
        $default = [
            'label' => '',
            'description' => '',
            'type' => 'text',
            'name' => '',
            'args' => [
                'disabled' => false,
                'readonly' => false,
                'value' => null,
                'placeholder' => '',
                'options' => [],
                'selected' => null,
                'checked' => null,
            ],
            'message' => '',
            'prefix' => '',
            'postfix' => '',
        ];
        $params = array_merge($default, $params);
        $params['name'] = static::NAME . '[' . $params['name'] . ']';

        $this->settingsField[$params['name']] = $params;
    }

    public function getSettingsFields()
    {
        return $this->settingsField;
    }

    protected function addToolbarItem($params = [])
    {
        $default = [
            'twig' => '',
            'html' => '',
        ];
        $params = array_merge($default, $params);

        $this->toolbars[] = $params;
    }

    public function getToolbarItem()
    {
        return $this->toolbars;
    }

    /**
     * Функция выполнится ДО обработки выбранной группы роутов
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return Response
     */
    public function execute(Request $request, Response $response): Response
    {
        return $response;
    }

    /**
     * Возвращает значение параметра по переданному ключу
     * Если передан массив ключей, возвращает массив найденных ключей и их значения
     *
     * @param string|string[] $key
     * @param mixed           $default
     *
     * @return array|string|mixed
     */
    protected function getParameter($key = null, $default = null)
    {
        return $this->container->get('parameter')->get($key, $default);
    }

    /**
     * @param string $template
     * @param array  $data
     *
     * @return string
     * @throws HttpBadRequestException
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     */
    protected function render($template, array $data = [])
    {

        try {
            \RunTracy\Helpers\Profiler\Profiler::start('plugin render (%s)', $template);


            $rendered = $this->renderer->fetch($template, $data);

            \RunTracy\Helpers\Profiler\Profiler::finish('plugin render (%s)', $template);

            return $rendered;
        } catch (\Twig\Error\LoaderError $exception) {
            throw new RuntimeException($exception->getMessage());
        }
    }
}
