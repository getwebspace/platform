<?php declare(strict_types=1);

namespace App\Domain;

use Slim\Http\Request;
use Slim\Http\Response;

abstract class AbstractMiddleware extends AbstractComponent
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param callable $next
     *
     * @return Response
     */
    abstract public function __invoke(Request $request, Response $response, $next): \Slim\Http\Response;
}