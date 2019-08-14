<?php

use Slim\Http\Request;
use Slim\Http\Response;

// http cache
$app->add(new \Slim\HttpCache\Cache('public', 86400));

$app->add(\Core\Common::class); // check load params
$app->add(\Core\Auth::class); // check auth

// redirect to address without slash in end
$app->add(function (Request $request, Response $response, $next) {
    $path = $request->getUri()->getPath();

    if ($path != '/' && str_ends_with('/', $path)) {
        return $response
            ->withStatus(308)
            ->withAddedHeader('Location', rtrim($path, '/'));
    }

    return $next($request, $response);
});
