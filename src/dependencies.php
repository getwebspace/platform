<?php declare(strict_types=1);

use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;

// doctrine
$container[\Doctrine\ORM\EntityManager::class] = function (ContainerInterface $c): EntityManager {
    $settings = $c->get('doctrine');

    foreach ($settings['types'] as $type => $class) {
        \Doctrine\DBAL\Types\Type::addType($type, $class);
    }

    $config = \Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration(
        $settings['meta']['entity_path'],
        $settings['meta']['auto_generate_proxies'],
        $settings['meta']['proxy_dir'],
        $settings['meta']['cache'],
        false
    );

    return \Doctrine\ORM\EntityManager::create($settings['connection'], $config);
};

// plugin control class
$container['plugin'] = function (ContainerInterface $c) {
    return new class {
        /** @var \Alksily\Entity\Collection */
        private $plugins;

        final public function __construct()
        {
            $this->plugins = collect();
        }

        /**
         * Register plugin
         *
         * @param \App\Application\Plugin $plugin
         *
         * @return array|mixed|string
         */
        final public function register(\App\Application\Plugin $plugin)
        {
            $class_name = get_class($plugin);

            if (!$this->plugins->has($class_name)) {
                $this->plugins->set($class_name, $plugin);

                return true;
            }

            return false;
        }

        final public function get()
        {
            return $this->plugins;
        }
    };
};

// wrapper around collection with params
$container['parameter'] = function (ContainerInterface $c) {
    \RunTracy\Helpers\Profiler\Profiler::start('parameters');

    /** @var \Alksily\Entity\Collection $parameters */
    static $parameters;

    if (!$parameters) {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $c->get(\Doctrine\ORM\EntityManager::class);

        try {
            /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $parametersRepository */
            $parametersRepository = $em->getRepository(\App\Domain\Entities\Parameter::class);
            $parameters = collect($parametersRepository->findAll());
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            $parameters = collect();
        }
    }

    \RunTracy\Helpers\Profiler\Profiler::finish('parameters');

    return new class($parameters) {
        /** @var \Alksily\Entity\Collection */
        private static $parameters;

        final public function __construct(\Alksily\Entity\Collection &$parameters)
        {
            static::$parameters = &$parameters;
        }

        /**
         * Return value by key
         * if key is array return array founded keys with values
         *
         * @param string|string[] $key
         * @param mixed           $default
         *
         * @return array|mixed|string
         */
        final public function get($key = null, $default = null)
        {
            if ($key === null) {
                return static::$parameters->mapWithKeys(function ($item) {
                    [$group, $key] = explode('_', $item->key, 2);

                    return [$group . '[' . $key . ']' => $item];
                });
            }
            if (is_string($key)) {
                return static::$parameters->firstWhere('key', $key)->value ?? $default;
            }

            return static::$parameters->whereIn('key', $key)->pluck('value', 'key')->all() ?? $default;
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

// push stream
$container['pushstream'] = function (ContainerInterface $c) {
    return new \App\Application\PushStream($c);
};

// monolog
$container['monolog'] = function (ContainerInterface $c) {
    $settings = $c->get('logger');

    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));

    return $logger;
};

// not found
$container['notFoundHandler'] = function (ContainerInterface $c) {
    return function (\Slim\Http\Request $request, \Slim\Http\Response $response) use ($c) {
        /** @var \Slim\Views\Twig $renderer */
        $renderer = $c->get('view');
        if (($path = realpath(THEME_DIR . '/' . $this->getParameter('common_theme', 'default'))) !== false) {
            $this->renderer->getLoader()->addPath($path);
            $response->getBody()->write($renderer->fetch('p404.twig'));
        } else {
            $response->getBody()->write('404');
        }
        $response->withStatus(404);

        return $response;
    };
};

// not allowed
$container['notAllowedHandler'] = function (ContainerInterface $c) {
    return function (\Slim\Http\Request $request, \Slim\Http\Response $response, $methods) use ($c) {
        /** @var \Slim\Views\Twig $renderer */
        $renderer = $c->get('view');
        if (($path = realpath(THEME_DIR . '/' . $this->getParameter('common_theme', 'default'))) !== false) {
            $this->renderer->getLoader()->addPath($path);
            $response->getBody()->write($renderer->fetch('p405.twig', ['methods' => $methods]));
        } else {
            $response->getBody()->write('405');
        }
        $response->withStatus(405);

        return $response;
    };
};

// error
$container['errorHandler'] = function (ContainerInterface $c) {
    return function (\Slim\Http\Request $request, \Slim\Http\Response $response, $exception) use ($c) {
        /** @var \Slim\Views\Twig $renderer */
        $renderer = $c->get('view');
        if (($path = realpath(THEME_DIR . '/' . $this->getParameter('common_theme', 'default'))) !== false) {
            $this->renderer->getLoader()->addPath($path);
            $response->getBody()->write($renderer->fetch('p500.twig', ['exception' => $exception]));
        } else {
            $response->getBody()->write('500');
        }
        $response->withStatus(500);

        return $response;
    };
};
