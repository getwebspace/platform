<?php declare(strict_types=1);

use DI\ContainerBuilder;
use Illuminate\Support\Collection;
use Psr\Container\ContainerInterface;

return function (ContainerBuilder $containerBuilder): void {
    // app
    $containerBuilder->addDefinitions([
        \Slim\App::class => function (ContainerInterface $container) {
            \Slim\Factory\AppFactory::setContainer($container);

            return \Slim\Factory\AppFactory::create();
        },

        \Slim\Interfaces\RouteCollectorInterface::class => function (ContainerInterface $container) {
            return $container->get(\Slim\App::class)->getRouteCollector();
        },

        // auth manager
        \App\Application\Auth::class => function (ContainerInterface $container) {
            return new \App\Application\Auth($container);
        },

        // pubsub component
        \App\Application\PubSub::class => function (ContainerInterface $container) {
            return new \App\Application\PubSub($container);
        },
    ]);

    // laravel parts
    $containerBuilder->addDefinitions([
        // database
        \Illuminate\Database\Connection::class => function (ContainerInterface $c): \Illuminate\Database\Connection {
            $settings = $c->get('database');

            if ($_ENV['TEST'] ?? false) {
                $settings['url'] = '';
                $settings['database'] = VAR_DIR . '/database-test.sqlite';
            }

            // sqlite takes a single writer at a time and fails the query outright
            // with "database is locked" when another one holds it - wait instead
            $settings['options'][PDO::ATTR_TIMEOUT] = 15;

            $capsule = new Illuminate\Database\Capsule\Manager();
            $capsule->addConnection($settings);

            // make this Capsule instance available globally via static methods
            $capsule->setAsGlobal();

            // setup the Eloquent ORM
            $capsule->bootEloquent();

            return $capsule->getConnection();
        },

        // array cache
        \Illuminate\Cache\ArrayStore::class => function (ContainerInterface $c): \Illuminate\Cache\ArrayStore {
            return new \Illuminate\Cache\ArrayStore();
        },

        // file cache
        \Illuminate\Cache\FileStore::class => function (ContainerInterface $c): \Illuminate\Cache\FileStore {
            return new \Illuminate\Cache\FileStore(new \Illuminate\Filesystem\Filesystem(), CACHE_DIR);
        },
    ]);

    // tnt search
    $containerBuilder->addDefinitions([
        \TeamTNT\TNTSearch\TNTSearch::class => function (ContainerInterface $c) {
            $pdo = $c->get(\Illuminate\Database\Connection::class)->getPdo();

            $tnt = new \TeamTNT\TNTSearch\TNTSearch();
            $tnt->setDatabaseHandle($pdo);
            $tnt->loadConfig([
                'storage' => VAR_DIR . '/cache',
                'engine' => \TeamTNT\TNTSearch\Engines\SqliteEngine::class,
            ]);
            $tnt->engine->setDatabaseHandle($pdo);
            $tnt->fuzziness(true);
            $tnt->engine->fuzzy_prefix_length = 2;
            $tnt->engine->fuzzy_max_expansions = 50;
            $tnt->engine->fuzzy_distance = 2;

            return $tnt;
        },
    ]);

    // scheduler
    $containerBuilder->addDefinitions([
        'scheduler' => function (ContainerInterface $c) {
            return new class($c) {
                private ContainerInterface $container;

                private Collection $jobs;

                final public function __construct(ContainerInterface $container)
                {
                    $this->container = $container;
                    $this->jobs = collect();
                }

                /**
                 * Register job
                 *
                 * @param mixed $schedule
                 */
                final public function register(\App\Domain\AbstractSchedule|string $job, $schedule = '* * * * *'): bool
                {
                    if (is_object($job)) {
                        $class_name = get_class($job);
                    } else {
                        $class_name = $job;
                        $job = new $job($this->container);
                    }

                    if (!$this->jobs->has($class_name)) {
                        $this->jobs[$class_name] = [
                            'schedule' => $schedule,
                            'job' => $job,
                        ];

                        return true;
                    }

                    return false;
                }

                final public function get(): Collection
                {
                    return $this->jobs;
                }
            };
        },
    ]);

    // plugins
    $containerBuilder->addDefinitions([
        'plugin' => function (ContainerInterface $c) {
            return new class($c) {
                private ContainerInterface $container;

                private Collection $plugins;

                final public function __construct(ContainerInterface $container)
                {
                    $this->container = $container;
                    $this->plugins = collect();
                }

                /**
                 * Register plugin
                 */
                final public function register(\App\Domain\AbstractPlugin|string $plugin): bool
                {
                    if (is_object($plugin)) {
                        $class_name = get_class($plugin);
                    } else {
                        $class_name = $plugin;
                        $plugin = new $plugin($this->container);
                    }

                    if (!$this->plugins->has($class_name)) {
                        $this->plugins[$class_name] = $plugin;

                        return true;
                    }

                    return false;
                }

                final public function get(): Collection
                {
                    return $this->plugins;
                }
            };
        },
    ]);

    // mcp tools
    $containerBuilder->addDefinitions([
        'mcp' => function (ContainerInterface $c) {
            return new class($c) {
                private ContainerInterface $container;

                /**
                 * @var array<string, \App\Domain\AbstractMcpTool|string>
                 */
                private array $tools = [];

                final public function __construct(ContainerInterface $container)
                {
                    $this->container = $container;

                    // built-in tools
                    $this->register(\App\Domain\McpTools\CatalogCategoryListTool::class);
                    $this->register(\App\Domain\McpTools\CatalogProductSearchTool::class);
                    $this->register(\App\Domain\McpTools\CatalogProductGetTool::class);
                    $this->register(\App\Domain\McpTools\CatalogOrderGetTool::class);
                    $this->register(\App\Domain\McpTools\CatalogOrderListTool::class);
                    $this->register(\App\Domain\McpTools\SiteOverviewTool::class);
                    $this->register(\App\Domain\McpTools\ReferenceListTool::class);
                    $this->register(\App\Domain\McpTools\FormDataListTool::class);
                    $this->register(\App\Domain\McpTools\FormDataGetTool::class);
                    $this->register(\App\Domain\McpTools\CatalogOrderUpdateStatusTool::class);
                    $this->register(\App\Domain\McpTools\ReviewListTool::class);
                    $this->register(\App\Domain\McpTools\ReviewModerateTool::class);
                    $this->register(\App\Domain\McpTools\GuestBookListTool::class);
                    $this->register(\App\Domain\McpTools\GuestBookModerateTool::class);
                    $this->register(\App\Domain\McpTools\TaskListTool::class);
                    $this->register(\App\Domain\McpTools\TaskRetryTool::class);
                }

                /**
                 * Register tool, instance is created on first use
                 */
                final public function register(\App\Domain\AbstractMcpTool|string $tool): bool
                {
                    $name = $tool::NAME;

                    if (!isset($this->tools[$name])) {
                        $this->tools[$name] = $tool;

                        return true;
                    }

                    return false;
                }

                final public function has(string $name): bool
                {
                    return isset($this->tools[$name]);
                }

                final public function find(string $name): ?\App\Domain\AbstractMcpTool
                {
                    if (!isset($this->tools[$name])) {
                        return null;
                    }
                    if (is_string($this->tools[$name])) {
                        $this->tools[$name] = new $this->tools[$name]($this->container);
                    }

                    return $this->tools[$name];
                }

                /**
                 * @return Collection<string, \App\Domain\AbstractMcpTool>
                 */
                final public function get(): Collection
                {
                    return collect(array_keys($this->tools))->mapWithKeys(fn ($name) => [$name => $this->find($name)]);
                }
            };
        },
    ]);

    // view twig file render
    $containerBuilder->addDefinitions([
        'view' => function (ContainerInterface $c) {
            $settings = array_merge(
                ['template_path' => VIEW_DIR],
                $c->get('twig'),
                ['displayErrorDetails' => $c->get('settings')['displayErrorDetails']]
            );

            $view = \Slim\Views\Twig::create($settings['template_path'], [
                'debug' => $settings['displayErrorDetails'],
                'cache' => $settings['displayErrorDetails'] ? false : $settings['caches_path'],
                'auto_reload' => $settings['displayErrorDetails'],
            ]);

            $view->addExtension(new \App\Application\TwigExtension($c));
            $view->addExtension(new \Twig\Extra\Intl\IntlExtension());
            $view->addExtension(new \Twig\Extra\String\StringExtension());
            $view->addExtension(new \Twig\Extension\StringLoaderExtension());

            // if debug
            if ($settings['displayErrorDetails']) {
                $view->addExtension(new \Twig\Extension\ProfilerExtension(new \Twig\Profiler\Profile()));
                $view->addExtension(new \Twig\Extension\DebugExtension());
            }

            return $view;
        },
    ]);

    // monolog
    $containerBuilder->addDefinitions([
        \Psr\Log\LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get('logger');

            $logger = new \Monolog\Logger($settings['name']);
            $rotatingHandler = new \Monolog\Handler\RotatingFileHandler($settings['path'], 5, $settings['level']);

            $dateFormat = 'Y-m-d H:i:s';
            $outputFormat = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
            $formatter = new \Monolog\Formatter\LineFormatter($outputFormat, $dateFormat);

            $rotatingHandler->setFormatter($formatter);

            $logger->pushHandler($rotatingHandler);

            return $logger;
        },
    ]);
};
