<?php declare(strict_types=1);

namespace App\Domain\Plugin;

use App\Domain\AbstractPlugin;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

abstract class AbstractLegacyPlugin extends AbstractPlugin
{
    /**
     * The function will be executed BEFORE processing the selected group of routes
     */
    public function before(Request $request, string $routeName): void
    {
        // empty method
    }

    /**
     * The function will be executed AFTER processing the selected group of routes
     */
    public function after(Request $request, Response $response, string $routeName): Response
    {
        return $response;
    }
}
