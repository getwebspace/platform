<?php declare(strict_types=1);

use Slim\Http\Request;
use Slim\Http\Response;

/**
 * @var \Slim\App $app
 */

// apply locale
$app->add(\App\Application\Middlewares\LocaleMiddleware::class);

// check user access
$app->add(\App\Application\Middlewares\AccessCheckerMiddleware::class);

// check user auth
$app->add(\App\Application\Middlewares\AuthorizationMiddleware::class);

// plugin functions
$app->add(\App\Application\Middlewares\PluginMiddleware::class);

// check is site disabled
$app->add(\App\Application\Middlewares\IsSiteEnabledMiddleware::class);

// RunTracy
$app->add(new RunTracy\Middlewares\TracyMiddleware($app));

// redirect to address without slash in end
$app->add(function (Request $request, Response $response, $next) {
    $path = $request->getUri()->getPath();

    if ($path !== '/' && str_end_with($path, '/')) {
        $query = $request->getUri()->getQuery();

        return $response->withRedirect(rtrim($path, '/') . ($query ? '?' . $query : ''));
    }

    return $next($request, $response);
});
