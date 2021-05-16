<?php declare(strict_types=1);

namespace App\Domain;

use Slim\Http\Request;
use Slim\Http\Response;

abstract class AbstractMiddleware extends AbstractComponent
{
    abstract public function __invoke(Request $request, Response $response, callable $next): \Slim\Http\Response;
}
