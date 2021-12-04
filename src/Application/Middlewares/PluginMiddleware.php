<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\AbstractPlugin;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;

class PluginMiddleware extends AbstractMiddleware
{
    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        /** @var \Illuminate\Support\Collection $plugins */
        $plugins = $this->container->get('plugin')->get();

        if ($plugins->count()) {
            // \RunTracy\Helpers\Profiler\Profiler::start('middleware:plugin');

            $plugins = $plugins->where('routes', true);

            /** @var \Slim\Interfaces\RouteInterface $route */
            $route = $request->getAttribute('route');
            $routeName = $route->getName();

            /** @var AbstractPlugin $plugin */
            foreach ($plugins as $plugin) {
                if ($routeName && str_start_with($routeName, $plugin->getHandledRoute())) {
                    // \RunTracy\Helpers\Profiler\Profiler::start('plugin');
                    $plugin->before($request, $route->getName());
                    // \RunTracy\Helpers\Profiler\Profiler::finish('%s', $plugin->getCredentials('name'));
                }
            }

            $response = $handler->handle($request);

            /** @var AbstractPlugin $plugin */
            foreach ($plugins as $plugin) {
                if ($routeName && str_start_with($routeName, $plugin->getHandledRoute())) {
                    \Netpromotion\Profiler\Profiler::start('plugin');
                    $response = $plugin->after($request, $response, $route->getName());
                    \Netpromotion\Profiler\Profiler::finish('%s', $plugin->getCredentials('name'));
                }
            }

            \Netpromotion\Profiler\Profiler::finish('middleware:plugin');

            return $response;
        }

        return $handler->handle($request);
    }
}
