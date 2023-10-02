<?php declare(strict_types=1);

namespace App\Domain;

use App\Application\i18n;
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

    private array $handledRoutes = [];

    private array $settingsField = [];

    public bool $toolbar = false;

    private array $toolbars = [];

    public bool $sidebar = false;

    private array $sidebars = [];

    public bool $navigation = false;

    public bool $routes = false;

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

    public function getCredentials(string $field = null): array|string
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
     */
    protected function setTemplateFolder(string $path): void
    {
        if (realpath($path) !== false) {
            $this->renderer->getLoader()->addPath($path);
        }
    }

    protected function setHandledRoute(...$name): void
    {
        $this->routes = true;
        $this->handledRoutes = array_merge($this->handledRoutes, $name);
    }

    public function getHandledRoute(): array
    {
        return $this->handledRoutes;
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
     */
    protected function addToolbarItem(array $params = []): void
    {
        $default = [
            'twig' => '',
            'html' => '',
        ];
        $params = array_merge($default, $params);

        $this->toolbar = true;

        $this->toolbars[] = $params;
    }

    public function getToolbarItem(): array
    {
        return $this->toolbars;
    }

    /**
     * Add sidebar tab
     */
    protected function addSidebarTab(array $params = []): void
    {
        $default = [
            'twig' => '',
            'html' => '',
        ];
        $params = array_merge($default, $params);

        $this->sidebar = true;

        $this->sidebars[] = $params;
    }

    public function getSidebarTab(): array
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

    public function isNavigationItemEnabled(): bool
    {
        return $this->navigation;
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

    /**
     * Add new line in current locale table
     */
    public function addLocale(string $code, array $strings = []): void
    {
        i18n::addLocale($code, $strings);
    }

    /**
     * Add new array of lines in current locale table
     */
    public function addLocaleFromFile(string $code, string $path): void
    {
        i18n::addLocaleFromFile($code, $path);
    }

    /**
     * Add locale editor words
     */
    public function addLocaleEditor(string $code, array $translate): void
    {
        i18n::addLocaleEditor($code, $translate);
    }

    /**
     * Add translate letters
     */
    public function addLocaleTranslateLetters(string $code, array $original, array $latin): void
    {
        i18n::addLocaleTranslateLetters($code, $original, $latin);
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
     * The function will be executed BEFORE processing the selected group of routes
     */
    public function before(Request $request, string $routeName): void
    {
        // empty method
    }

    /**
     * The function will be executed AFTER processing the selected group of routes
     */
    public function after(Request $request, Response $response, string $routeName): Response
    {
        return $response;
    }
}
