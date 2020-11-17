<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\AbstractPlugin;
use Slim\Http\Request;
use Slim\Http\Response;

class PluginMiddleware extends AbstractMiddleware
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param callable $next
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $next): \Slim\Http\Response
    {
        \RunTracy\Helpers\Profiler\Profiler::start('middleware:plugin');

        /** @var \Slim\Interfaces\RouteInterface $route */
        $route = $request->getAttribute('route');
        $routeName = $route->getName();

        /** @var \Illuminate\Support\Collection $plugins */
        $plugins = $this->container->get('plugin')->get()->where('routes', true);

        /** @var AbstractPlugin $plugin */
        foreach ($plugins as $plugin) {
            if ($routeName && str_start_with($routeName, $plugin->getRoute())) {
                \RunTracy\Helpers\Profiler\Profiler::start('plugin (%s)', $plugin->getCredentials('name'));
                $response = $plugin->before($request, $response, $route->getName());
                \RunTracy\Helpers\Profiler\Profiler::finish('plugin (%s)', $plugin->getCredentials('name'));
            }
        }

        $response = $next($request, $response);

        /** @var AbstractPlugin $plugin */
        foreach ($plugins as $plugin) {
            if ($routeName && str_start_with($routeName, $plugin->getRoute())) {
                \RunTracy\Helpers\Profiler\Profiler::start('plugin (%s)', $plugin->getCredentials('name'));
                $response = $plugin->after($request, $response, $route->getName());
                \RunTracy\Helpers\Profiler\Profiler::finish('plugin (%s)', $plugin->getCredentials('name'));
            }
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('middleware:plugin');

        return $response;
    }
}
