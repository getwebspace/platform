<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\AbstractPlugin;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;
use Slim\Routing\RouteContext;

class PluginMiddleware extends AbstractMiddleware
{
    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        /** @var \Illuminate\Support\Collection $plugins */
        $plugins = $this->container->get('plugin')
            ->get()
            ->filter(fn ($el) => $el instanceof \App\Domain\Plugin\AbstractLegacyPlugin);

        if ($plugins->count()) {
            $routeContext = RouteContext::fromRequest($request);
            $route = $routeContext->getRoute();
            $routeName = $route->getName();

            /** @var AbstractPlugin $plugin */
            foreach ($plugins as $plugin) {
                foreach ($plugin->getHandledRoute() as $r) {
                    if ($routeName && str_starts_with($routeName, $r)) {
                        $plugin->before($request, $routeName);
                    }
                }
            }

            $response = $handler->handle($request);

            /** @var AbstractPlugin $plugin */
            foreach ($plugins as $plugin) {
                foreach ($plugin->getHandledRoute() as $r) {
                    if ($routeName && str_starts_with($routeName, $r)) {
                        $response = $plugin->after($request, $response, $routeName);
                    }
                }
            }

            return $response;
        }

        return $handler->handle($request);
    }
}
