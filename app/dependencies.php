<?php

use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;

// doctrine
$container[\Doctrine\ORM\EntityManager::class] = function (ContainerInterface $c) : EntityManager {
    $settings = $c->get('settings')['doctrine'];

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
        $c->get('settings')['renderer'],
        $c->get('settings')['twig'],
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
    $view['_error'] = \AEngine\Support\Form::$globalError;
    //$view['parameter'] = \Core\Common::$parameter;
    //$view['user'] = \Core\Auth::$user;

    $view->addExtension(
        new \Slim\Views\TwigExtension(
            $c->get('router'),
            \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER))
        )
    );
    //$view->addExtension(new \Core\TwigExtension());
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
    $settings = $c->get('settings')['logger'];

    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));

    return $logger;
};

// not found
$container['notFoundHandler'] = function ($c) {
    return function ($request, $response) use ($c) {
        return $c
            ->template
            ->render($response, 'p404.twig')
            ->withStatus(404)
        ;
    };
};

// not allowed
$container['notAllowedHandler'] = function ($c) {
    return function ($request, $response, $methods) use ($c) {
        return $c
            ->template
            ->render($response, 'p405.twig', ['methods' => $methods])
            ->withStatus(404)
        ;
    };
};

// error
$container['errorHandler'] = function ($c) {
    return function ($request, $response, $exception) use ($c) {
        return $c
            ->template
            ->render($response, 'p500.twig', ['exception' => $exception])
            ->withStatus(404)
        ;
    };
};

// resource parameter
$container[\Resource\Parameter::class] = function ($c) {
    return new \Resource\Parameter($c->get('em'), $c->get('logger'));
};

// resource user
$container[\Resource\User::class] = function ($c) {
    return new \Resource\User($c->get('em'), $c->get('logger'));
};

// resource user session
$container[\Resource\User\Session::class] = function ($c) {
    return new \Resource\User\Session($c->get('em'), $c->get('logger'));
};

// resource page
$container[\Resource\Page::class] = function ($c) {
    return new \Resource\Page($c->get('em'), $c->get('logger'));
};

// resource publication
$container[\Resource\Publication::class] = function ($c) {
    return new \Resource\Publication($c->get('em'), $c->get('logger'));
};

// resource publication category
$container[\Resource\Publication\Category::class] = function ($c) {
    return new \Resource\Publication\Category($c->get('em'), $c->get('logger'));
};

// resource form
$container[\Resource\Form::class] = function ($c) {
    return new \Resource\Form($c->get('em'), $c->get('logger'));
};

// resource form data
$container[\Resource\Form\Data::class] = function ($c) {
    return new \Resource\Form\Data($c->get('em'), $c->get('logger'));
};

// resource files data
$container[\Resource\File::class] = function ($c) {
    return new \Resource\File($c->get('em'), $c->get('logger'));
};

// resource catalog category
$container[\Resource\Catalog\Category::class] = function ($c) {
    return new \Resource\Catalog\Category($c->get('em'), $c->get('logger'));
};

// resource catalog product
$container[\Resource\Catalog\Product::class] = function ($c) {
    return new \Resource\Catalog\Product($c->get('em'), $c->get('logger'));
};

// resource guestbook
$container[\Resource\GuestBook::class] = function ($c) {
    return new \Resource\GuestBook($c->get('em'), $c->get('logger'));
};
