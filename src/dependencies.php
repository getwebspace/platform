<?php declare(strict_types=1);

use Doctrine\ORM\EntityManager;
use Illuminate\Support\Collection;
use Psr\Container\ContainerInterface;

/**
 * @var \Slim\App $app
 */

// doctrine
$container[\Doctrine\ORM\EntityManager::class] = function (ContainerInterface $c): EntityManager {
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
};

// plugins
$container['plugin'] = function (ContainerInterface $c) {
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
};

// view twig file render
$container['view'] = function (ContainerInterface $c) {
    $settings = array_merge(
        ['template_path' => VIEW_DIR],
        $c->get('twig'),
        ['displayErrorDetails' => $c->get('settings')['displayErrorDetails']]
    );

    $view = new \Slim\Views\Twig($settings['template_path'], [
        'debug' => $settings['displayErrorDetails'],
    ]);

    $view->addExtension(new \App\Application\TwigExtension($c, \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER))));
    $view->addExtension(new \Twig\Extra\Intl\IntlExtension());
    $view->addExtension(new \Twig_Extensions_Extension_Text());
    $view->addExtension(new \Twig\Extension\StringLoaderExtension());
    $view->addExtension(new \Phive\Twig\Extensions\Deferred\DeferredExtension());
    $view->addExtension(new \nochso\HtmlCompressTwig\Extension());

    // if debug
    if ($settings['displayErrorDetails']) {
        $view->addExtension(new \Twig\Extension\ProfilerExtension($c['twig_profile']));
        $view->addExtension(new \Twig\Extension\DebugExtension());
    }

    // set cache path
    if (!$settings['displayErrorDetails']) {
        $env = $view->getEnvironment();
        $env->setCache($settings['caches_path']);
    }

    return $view;
};

// twig profile
$container['twig_profile'] = function (ContainerInterface $c) {
    return new \Twig\Profiler\Profile();
};

// monolog
$container['monolog'] = function (ContainerInterface $c) {
    $settings = $c->get('logger');

    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));

    return $logger;
};
