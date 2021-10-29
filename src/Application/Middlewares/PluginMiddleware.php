<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\AbstractPlugin;
use Slim\Http\Request;
use Slim\Http\Response;

class PluginMiddleware extends AbstractMiddleware
{
    /**
     * @param callable $next
     *
     * @throws \Exception
     */
    public function __invoke(Request $request, Response $response, $next): \Slim\Http\Response
    {
        /** @var \Illuminate\Support\Collection $plugins */
        $plugins = $this->container->get('plugin')->get();

        if ($plugins->count()) {
            \RunTracy\Helpers\Profiler\Profiler::start('middleware:plugin');

            $plugins = $plugins->where('routes', true);

            /** @var \Slim\Interfaces\RouteInterface $route */
            $route = $request->getAttribute('route');
            $routeName = $route->getName();

            /** @var AbstractPlugin $plugin */
            foreach ($plugins as $plugin) {
                if ($routeName && str_start_with($routeName, $plugin->getHandledRoute())) {
                    \RunTracy\Helpers\Profiler\Profiler::start('plugin');
                    $response = $plugin->before($request, $response, $route->getName());
                    \RunTracy\Helpers\Profiler\Profiler::finish('%s', $plugin->getCredentials('name'));
                }
            }

            $response = $next($request, $response);

            /** @var AbstractPlugin $plugin */
            foreach ($plugins as $plugin) {
                if ($routeName && str_start_with($routeName, $plugin->getHandledRoute())) {
                    \RunTracy\Helpers\Profiler\Profiler::start('plugin');
                    $response = $plugin->after($request, $response, $route->getName());
                    \RunTracy\Helpers\Profiler\Profiler::finish('%s', $plugin->getCredentials('name'));
                }
            }

            \RunTracy\Helpers\Profiler\Profiler::finish('middleware:plugin');

            return $response;
        }

        return $next($request, $response);
    }
}
