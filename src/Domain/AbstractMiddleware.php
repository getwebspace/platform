<?php declare(strict_types=1);

namespace App\Domain;

use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;

abstract class AbstractMiddleware extends AbstractComponent
{
    abstract public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response;
}
