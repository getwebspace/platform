<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;

class IsSiteEnabledMiddleware extends AbstractMiddleware
{
    /**
     * @param callable $next
     *
     * @throws \Exception
     */
    public function __invoke(Request $request, Response $response, $next): \Slim\Http\Response
    {
        /** @var \Slim\Interfaces\RouteInterface $route */
        $route = $request->getAttribute('route');

        if (str_start_with($route->getName(), 'common:') && $this->parameter('common_site_enabled', 'yes') !== 'yes') {
            $renderer = $this->container->get('view');

            // add default errors pages
            $renderer->getLoader()->addPath(VIEW_ERROR_DIR);

            return $response
                ->write($renderer->fetch('p503.twig'))
                ->withStatus(503);
        }

        return $next($request, $response);
    }
}
