<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

class IsSiteEnabledMiddleware extends AbstractMiddleware
{
    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        if (str_start_with($route->getName(), 'common:') && $this->parameter('common_site_enabled', 'yes') !== 'yes') {
            $renderer = $this->container->get('view');

            if (($path = realpath(THEME_DIR . '/' . $this->parameter('common_theme', 'default'))) !== false) {
                $renderer->getLoader()->addPath($path);
            }

            // add default errors pages
            $renderer->getLoader()->addPath(VIEW_ERROR_DIR);

            return (new Response())
                ->write($renderer->fetch('p503.twig'))
                ->withStatus(503);
        }

        return $handler->handle($request);
    }
}
