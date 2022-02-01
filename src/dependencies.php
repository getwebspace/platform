<?php declare(strict_types=1);

use DI\ContainerBuilder;
use Doctrine\ORM\EntityManager;
use Illuminate\Support\Collection;
use Psr\Container\ContainerInterface;
use Twig\DeferredExtension\DeferredExtension;
use voku\helper\HtmlMin;
use voku\twig\MinifyHtmlExtension;

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
    ]);

    // doctrine
    $containerBuilder->addDefinitions([
        \Doctrine\ORM\EntityManager::class => function (ContainerInterface $c): EntityManager {
            $settings = $c->get('doctrine');

            foreach ($settings['types'] as $type => $class) {
                if (!\Doctrine\DBAL\Types\Type::hasType($type)) {
                    \Doctrine\DBAL\Types\Type::addType($type, $class);
                } else {
                    \Doctrine\DBAL\Types\Type::overrideType($type, $class);
                }
            }

            $config = \Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration(
                $settings['meta']['entity_path'],
                $settings['meta']['auto_generate_proxies'],
                $settings['meta']['proxy_dir'],
                $settings['meta']['cache'],
                false
            );

            $em = \Doctrine\ORM\EntityManager::create($settings['connection'], $config);
            $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

            return $em;
        },
    ]);

    // plugins
    $containerBuilder->addDefinitions([
        'plugin' => function (ContainerInterface $c) {
            return new class() {
                private Collection $plugins;

                final public function __construct()
                {
                    $this->plugins = collect();
                }

                /**
                 * Register plugin
                 *
                 * @return array|mixed|string
                 */
                final public function register(\App\Domain\AbstractPlugin $plugin)
                {
                    $class_name = get_class($plugin);

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
                'cache' => $settings['caches_path'],
                'auto_reload' => $settings['displayErrorDetails'],
            ]);

            $view->addExtension(new \App\Application\TwigExtension($c));
            $view->addExtension(new \Twig\Extra\Intl\IntlExtension());
            $view->addExtension(new \Twig\Extra\String\StringExtension());
            $view->addExtension(new \Twig\Extension\StringLoaderExtension());
            $view->addExtension(new DeferredExtension());
            $view->addExtension(new MinifyHtmlExtension(new HtmlMin()));

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

            $logger = new Monolog\Logger($settings['name']);
            $logger->pushProcessor(new Monolog\Processor\UidProcessor());
            $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));

            return $logger;
        },
    ]);
};
