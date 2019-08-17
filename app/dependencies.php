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

// view twig file render
$container[\Slim\Views\Twig::class] = function (ContainerInterface $c) {
    $settings = array_merge(
        $c->get('renderer'),
        $c->get('twig'),
        [
            'displayErrorDetails' => $c->get('settings')['displayErrorDetails'],
        ]
    );

    $view = new \Slim\Views\Twig($settings['template_path'], [
        'debug' => $settings['displayErrorDetails'],
    ]);

    $view['_request'] = $_REQUEST;
    $view['styles'] = new ArrayObject();
    $view['scripts'] = new ArrayObject();

    $view->addExtension(
        new \Slim\Views\TwigExtension(
            $c->get('router'),
            \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER))
        )
    );
    $view->addExtension(new \Application\TwigExtension());
    $view->addExtension(new Twig\Extra\Intl\IntlExtension());
    $view->addExtension(new Twig_Extensions_Extension_Text());
    $view->addExtension(new Twig\Extension\StringLoaderExtension());
    $view->addExtension(new Phive\Twig\Extensions\Deferred\DeferredExtension());

    // if debug
    if ($settings['displayErrorDetails']) {
        $view->addExtension(new \Twig\Extension\DebugExtension());
    }

    // set cache path
    if (!$settings['displayErrorDetails']) {
        $env = $view->getEnvironment();
        $env->setCache($settings['caches_path']);
    }

    return $view;
};

// monolog
$container[\Monolog\Logger::class] = function (ContainerInterface $c) {
    $settings = $c->get('logger');

    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));

    return $logger;
};

// not found
$container['notFoundHandler'] = function ($c) {
    return function ($request, $response) use ($c) {
        return $c->get(\Slim\Views\Twig::class)->render($response, 'p404.twig')->withStatus(404)
        ;
    };
};

// not allowed
$container['notAllowedHandler'] = function ($c) {
    return function ($request, $response, $methods) use ($c) {
        return $c->get(\Slim\Views\Twig::class)->render($response, 'p405.twig', ['methods' => $methods])->withStatus(401);
    };
};

// error
$container['errorHandler'] = function ($c) {
    return function ($request, $response, $exception) use ($c) {
        return $c->get(\Slim\Views\Twig::class)->render($response, 'p500.twig', ['exception' => $exception])->withStatus(500)
        ;
    };
};
