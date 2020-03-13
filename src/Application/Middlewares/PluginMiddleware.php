<?php

namespace App\Application\Middlewares;

use Slim\Http\Request;
use Slim\Http\Response;

class PluginMiddleware extends Middleware
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param callable $next
     *
     * @return Response
     * @throws \Exception
     */
    public function __invoke(Request $request, Response $response, $next): \Slim\Http\Response
    {
        \RunTracy\Helpers\Profiler\Profiler::start('middleware:plugin');

        /** @var \Slim\Interfaces\RouteInterface $route */
        $route = $request->getAttribute('route');

        /** @var \Alksily\Entity\Collection $plugins */
        $plugins = $this->container->get('plugin')->get();

        /** @var \App\Application\Plugin $plugin */
        foreach ($plugins as $plugin) {
            if (in_array($route->getName(), $plugin->getRoute())) {
                \RunTracy\Helpers\Profiler\Profiler::start('plugin (%s)', get_class($plugin));
                $response = $plugin->execute($request, $response);
                \RunTracy\Helpers\Profiler\Profiler::finish('plugin (%s)', get_class($plugin));
            }
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('middleware:plugin');

        return  $next($request, $response);
    }
}
