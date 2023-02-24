<?php declare(strict_types=1);

use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

return function (App $app): void {
    // plugin functions
    $app->add(\App\Application\Middlewares\PluginMiddleware::class);

    // redirect to non-www domain
    $app->add(\App\Application\Middlewares\NonWWWMiddleware::class);

    // cors headers
    $app->add(\App\Application\Middlewares\CORSMiddleware::class);

    // redirect to address without slash in end
    $app->add(function (Request $request, RequestHandlerInterface $handler) {
        $path = $request->getUri()->getPath();

        if ($path !== '/' && str_ends_with($path, '/')) {
            $query = $request->getUri()->getQuery();

            return (new Response())
                ->withAddedHeader('Location', rtrim($path, '/') . ($query ? '?' . $query : ''))
                ->withStatus(301);
        }

        return $handler->handle($request);
    });
};
