<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\ParameterTrait;
use App\Domain\Traits\RendererTrait;
use App\Domain\Traits\StorageTrait;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Interfaces\RouteCollectorInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

abstract class AbstractPlugin
{
    use ParameterTrait;
    use RendererTrait;
    use StorageTrait;

    public const NAME = 'UntitledPlugin';
    public const TITLE = 'Untitled plugin';
    public const DESCRIPTION = '';
    public const AUTHOR = 'Undefined author';
    public const AUTHOR_EMAIL = '';
    public const AUTHOR_SITE = '';
    public const VERSION = '1.0';

    protected ContainerInterface $container;

    protected LoggerInterface $logger;

    private RouteCollectorInterface $router;

    private array $settingsField = [];

    public bool $script = false;

    private array $scripts = [];

    public bool $toolbar = false;

    private array $toolbars = [];

    public bool $sidebar = false;

    private array $sidebars = [];

    public bool $navigation = false;

    /**
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __construct(ContainerInterface $container)
    {
        if (empty(static::NAME) || empty(static::TITLE) || empty(static::AUTHOR)) {
            throw new \RuntimeException('Plugin credentials have empty fields');
        }

        $this->container = $container;
        $this->container->set(static::NAME, $this);
        $this->logger = $container->get(LoggerInterface::class);
        $this->router = $container->get(App::class)->getRouteCollector();
        $this->renderer = $container->get('view');
    }

    public function getCredentials(string $field = null): array|string|null
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
     * Publish a data to a channel
     */
    public function publish(string $channel, mixed $data = []): self
    {
        $this->container->get(\App\Application\PubSub::class)->publish($channel, $data);

        return $this;
    }

    /**
     * Subscribe a handler to a channel
     */
    public function subscribe(string|array $channels, callable|array $handler): self
    {
        $this->container->get(\App\Application\PubSub::class)->subscribe($channels, $handler);

        return $this;
    }

    /**
     * Register specific twig templates path
     */
    protected function setTemplateFolder(string $path): void
    {
        if (realpath($path) !== false) {
            $this->renderer->getLoader()->addPath($path);
        }
    }

    /**
     * Register twig specific extension
     */
    protected function addTwigExtension(string $extension): void
    {
        $this->renderer->addExtension(new $extension($this->container));
    }

    /**
     * Add plugin setting field
     */
    protected function addSettingsField(array $params = []): void
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
                'option' => [],
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
     * Add script element
     */
    protected function addScript(array|string $params = []): void
    {
        $this->script = true;
        $this->scripts[] = is_array($params) ? array_first($params) : $params;
    }

    public function getScripts(): array
    {
        return $this->scripts;
    }

    /**
     * Add toolbar button
     */
    protected function addToolbarItem(array|string $params = []): void
    {
        $this->toolbar = true;
        $this->toolbars[] = is_array($params) ? array_first($params) : $params;
    }

    public function getToolbarItems(): array
    {
        return $this->toolbars;
    }

    /**
     * Add sidebar tab
     */
    protected function addSidebarTab(array|string $params = []): void
    {
        $this->sidebar = true;
        $this->sidebars[] = is_array($params) ? array_first($params) : $params;
    }

    public function getSidebarTabs(): array
    {
        return $this->sidebars;
    }

    /**
     * Add navigation menu point
     */
    protected function enableNavigationItem(array $params = []): RouteInterface
    {
        $self = $this;
        $default = [
            'handler' => function (Request $request, Response $response) use ($self) {
                $response = $response->withHeader('Content-Type', 'text/plain');
                $response->getBody()->write(
                    'This is empty route for plugin: ' . $self::NAME . PHP_EOL .
                    'Change "handler" key in function arguments enableNavigationItem(["handler" => ??]).'
                );

                return $response;
            },
        ];
        $params = array_merge($default, $params);

        $this->navigation = true;

        return $this->router
            ->map(['GET', 'POST'], '/cup/plugin/' . static::NAME, $params['handler'])
            ->setName('cup:' . mb_strtolower(static::NAME));
    }

    protected function map(array $params): RouteInterface
    {
        $default = [
            'methods' => ['GET', 'POST'],
            'pattern' => '',
            'handler' => function (Request $request, Response $response) {
                $response = $response->withHeader('Content-Type', 'text/plain');
                $response->getBody()->write(
                    'This is empty route for plugin: ' . static::NAME . PHP_EOL .
                    'Change "handler" key in function arguments map(["methods" => "..", "pattern" => "..", "handler" => ??]).'
                );

                return $response;
            },
        ];
        $params = array_merge($default, $params);

        if (!is_array($params['methods'])) {
            $params['methods'] = [$params['methods']];
        }

        return $this->router
            ->map(array_map('mb_strtoupper', $params['methods']), (string) $params['pattern'], $params['handler'])
            ->add(\App\Application\Middlewares\AccessCheckerMiddleware::class)
            ->add(\App\Application\Middlewares\AuthorizationMiddleware::class)
            ->add(\App\Application\Middlewares\CORSMiddleware::class);
    }
}
