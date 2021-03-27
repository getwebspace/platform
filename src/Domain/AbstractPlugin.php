<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exceptions\HttpBadRequestException;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

abstract class AbstractPlugin extends AbstractComponent
{
    public const NAME          = 'UntitledPlugin';
    public const TITLE         = 'Untitled plugin';
    public const DESCRIPTION   = '';
    public const AUTHOR        = 'Undefined author';
    public const AUTHOR_EMAIL  = '';
    public const AUTHOR_SITE   = '';
    public const VERSION       = '1.0';

    /**
     * @var \Slim\Router
     */
    private \Slim\Router $router;

    /**
     * @var Twig
     */
    private Twig $renderer;

    /**
     * @var string
     */
    private string $templateFolder;

    /**
     * @var array
     */
    private array $handledRoutes = [];

    /**
     * @var array
     */
    private array $settingsField = [];

    /**
     * @var array
     */
    private array $toolbars = [];

    /**
     * @var bool
     */
    public bool $routes = false;

    /**
     * @var bool
     */
    public bool $navigation = false;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        if (empty(static::NAME) || empty(static::TITLE) || empty(static::AUTHOR)) {
            throw new RuntimeException('Plugin credentials have empty fields');
        }

        $this->container[static::NAME] = $this;
        $this->router = $this->container->get('router');
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

        if (in_array($field, array_keys($credentials), true)) {
            return $credentials[$field];
        }

        return $credentials;
    }

    /**
     * Register specific twig templates path
     *
     * @param string $path
     *
     * @throws \Twig\Error\LoaderError
     */
    protected function setTemplateFolder(string $path): void
    {
        if (realpath($path) !== false) {
            $this->renderer->getLoader()->addPath($path);
        }
    }

    public function getTemplateFolder(): string
    {
        return $this->templateFolder;
    }

    /**
     * @param mixed ...$name
     */
    protected function setHandledRoute(...$name): void
    {
        $this->routes = true;
        $this->handledRoutes = array_merge($this->handledRoutes, $name);
    }

    /**
     * @return array
     */
    public function getHandledRoute(): array
    {
        return $this->handledRoutes;
    }

    /**
     * Register twig specific extension
     *
     * @param $extension
     */
    protected function addTwigExtension($extension): void
    {
        $this->renderer->addExtension(new $extension($this->container));
    }

    /**
     * Add plugin setting field
     *
     * @param array $params
     */
    protected function addSettingsField($params = []): void
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
                'force-value' => null,
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

    public function getSettingsFields(): array
    {
        return $this->settingsField;
    }

    /**
     * Add toolbar button
     *
     * @param array $params
     */
    protected function addToolbarItem($params = []): void
    {
        $default = [
            'twig' => '',
            'html' => '',
        ];
        $params = array_merge($default, $params);

        $this->toolbars[] = $params;
    }

    public function getToolbarItem(): array
    {
        return $this->toolbars;
    }

    /**
     * Add sidebar menu point
     *
     * @param array $params route handler
     *
     * @return \Slim\Interfaces\RouteInterface|\Slim\Route
     */
    protected function enableNavigationItem($params = [])
    {
        $default = [
            'handler' => function (Request $req, Response $res) {
                return $res->withHeader('Content-Type', 'text/plain')->write(
                    'This is empty route for plugin: ' . static::NAME . PHP_EOL .
                    'Change "handler" key in function arguments enableNavigationItem(["handler" => ??]).'
                );
            },
        ];
        $params = array_merge($default, $params);

        $this->navigation = true;

        return $this->router->map(['get', 'post'], '/cup/plugin/' . static::NAME, $params['handler']);
    }

    public function isNavigationItemEnabled(): bool
    {
        return $this->navigation;
    }

    /**
     * @param array $params
     *
     * @return \Slim\Interfaces\RouteInterface|\Slim\Route
     */
    protected function map(array $params)
    {
        $default = [
            'methods' => ['get', 'post'],
            'pattern' => '',
            'handler' => function (Request $req, Response $res) {
                return $res->withHeader('Content-Type', 'text/plain')->write(
                    'This is empty route for plugin: ' . static::NAME . PHP_EOL .
                    'Change "handler" key in function arguments map(["methods" => "..", "pattern" => "..", "handler" => ??]).'
                );
            },
        ];
        $params = array_merge($default, $params);

        if (!is_array($params['methods'])) {
            $params['methods'] = [$params['methods']];
        }

        return $this->router->map($params['methods'], $params['pattern'], $params['handler']);
    }

    /**
     * The function will be executed BEFORE processing the selected group of routes
     *
     * @param Request  $request
     * @param Response $response
     * @param string   $routeName
     *
     * @return Response
     */
    public function before(Request $request, Response $response, string $routeName): Response
    {
        return $response;
    }

    /**
     * The function will be executed AFTER processing the selected group of routes
     *
     * @param Request  $request
     * @param Response $response
     * @param string   $routeName
     *
     * @return Response
     */
    public function after(Request $request, Response $response, string $routeName): Response
    {
        return $response;
    }

    /**
     * @param string $template
     * @param array  $data
     *
     * @throws HttpBadRequestException
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     *
     * @return string
     */
    protected function render($template, array $data = []): string
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
