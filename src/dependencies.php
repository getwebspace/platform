<?php

use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;

// doctrine
$container[\Doctrine\ORM\EntityManager::class] = function (ContainerInterface $c) : EntityManager {
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

    return new class($parameters)
    {
        /** @var \Alksily\Entity\Collection */
        private static $parameters;

        public final function __construct(\Alksily\Entity\Collection &$parameters)
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
         * @return array|string|mixed
         */
        public final function get($key = null, $default = null)
        {
            //
            if ($key === null) {
                return static::$parameters->mapWithKeys(function ($item) {
                    list($group, $key) = explode('_', $item->key, 2);

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

// trademaster api
$container['trademaster'] = function (ContainerInterface $c) {
    return new \App\Application\TradeMaster($c);
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
        $renderer->getLoader()->addPath(THEME_DIR . '/' . $c->get('parameter')->get('common_theme', 'default'));
        $response->getBody()->write($renderer->fetch('p404.twig'));
        $response->withStatus(404);

        return $response;
    };
};

// not allowed
$container['notAllowedHandler'] = function (ContainerInterface $c) {
    return function (\Slim\Http\Request $request, \Slim\Http\Response $response, $methods) use ($c) {
        /** @var \Slim\Views\Twig $renderer */
        $renderer = $c->get('view');
        $renderer->getLoader()->addPath(THEME_DIR . '/' . $c->get('parameter')->get('common_theme', 'default'));
        $response->getBody()->write($renderer->fetch('p405.twig', ['methods' => $methods]));
        $response->withStatus(405);

        return $response;
    };
};

// error
$container['errorHandler'] = function (ContainerInterface $c) {
    return function (\Slim\Http\Request $request, \Slim\Http\Response $response, $exception) use ($c) {
        /** @var \Slim\Views\Twig $renderer */
        $renderer = $c->get('view');
        $renderer->getLoader()->addPath(THEME_DIR . '/' . $c->get('parameter')->get('common_theme', 'default'));
        $response->getBody()->write($renderer->fetch('p500.twig', ['exception' => $exception]));
        $response->withStatus(500);

        return $response;
    };
};
