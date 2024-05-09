<?php declare(strict_types=1);

namespace App\Domain\Plugin;

use App\Domain\AbstractPlugin;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

abstract class AbstractLegacyPlugin extends AbstractPlugin
{
    public bool $routes = false;

    private array $handledRoutes = [];

    protected function setHandledRoute(...$name): void
    {
        $this->routes = true;
        $this->handledRoutes = array_merge($this->handledRoutes, $name);
    }

    public function getHandledRoute(): array
    {
        return $this->handledRoutes;
    }

    /**
     * The function will be executed BEFORE processing the selected route
     */
    abstract public function before(Request $request, string $routeName): void;

    /**
     * The function will be executed AFTER processing the selected route
     */
    abstract public function after(Request $request, Response $response, string $routeName): Response;
}
