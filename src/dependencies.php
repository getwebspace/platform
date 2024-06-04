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

    // eloquent database
    $containerBuilder->addDefinitions([
        \Illuminate\Database\Connection::class => function (ContainerInterface $c): \Illuminate\Database\Connection {
            $settings = $c->get('database');

            if ($_ENV['TEST'] ?? false) {
                $settings['url'] = '';
                $settings['database'] = VAR_DIR . '/database-test.sqlite';
            }

            $capsule = new Illuminate\Database\Capsule\Manager();
            $capsule->addConnection($settings);

            // make this Capsule instance available globally via static methods
            $capsule->setAsGlobal();

            // setup the Eloquent ORM
            $capsule->bootEloquent();

            return $capsule->getConnection();
        },
    ]);

    // simfony cache
    $containerBuilder->addDefinitions([
        \Symfony\Component\Cache\Adapter\ArrayAdapter::class => function (ContainerInterface $c): \Symfony\Component\Cache\Adapter\ArrayAdapter {
            $cache = new Symfony\Component\Cache\Adapter\ArrayAdapter(0, false);
            $cache->setLogger($c->get(\Psr\Log\LoggerInterface::class));

            return $cache;
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
                'stemmer' => \TeamTNT\TNTSearch\Stemmer\PorterStemmer::class,
            ]);
            $tnt->engine->setDatabaseHandle($pdo);
            $tnt->fuzziness(true);
            $tnt->engine->fuzzy_prefix_length = 2;
            $tnt->engine->fuzzy_max_expansions = 50;
            $tnt->engine->fuzzy_distance = 2;

            return $tnt;
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
            $view->addExtension(new \Twig\DeferredExtension\DeferredExtension());
            $view->addExtension(new \voku\twig\MinifyHtmlExtension(new \voku\helper\HtmlMin()));

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
            $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));

            return $logger;
        },
    ]);
};
