<?php declare(strict_types=1);

use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

return function (App $app): void {
    $c = $app->getContainer();

    // apply locale
    $app->add(new \App\Application\Middlewares\LocaleMiddleware($c));

    // check user access
    $app->add(new \App\Application\Middlewares\AccessCheckerMiddleware($c));

    // plugin functions
    $app->add(new \App\Application\Middlewares\PluginMiddleware($c));

    // check user auth
    $app->add(new \App\Application\Middlewares\AuthorizationMiddleware($c));

    // redirect to non-www domain
    $app->add(new \App\Application\Middlewares\NonWWWMiddleware($c));

    // check is site disabled
    $app->add(new \App\Application\Middlewares\IsSiteEnabledMiddleware($c));

    // redirect to address without slash in end
    $app->add(function (Request $request, RequestHandlerInterface $handler) {
        $path = $request->getUri()->getPath();

        if ($path !== '/' && str_end_with($path, '/')) {
            $query = $request->getUri()->getQuery();

            return (new Response())
                ->withAddedHeader('Location', rtrim($path, '/') . ($query ? '?' . $query : ''))
                ->withStatus(301);
        }

        return $handler->handle($request);
    });
};
