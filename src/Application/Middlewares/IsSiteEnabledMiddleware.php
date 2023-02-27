<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class IsSiteEnabledMiddleware extends AbstractMiddleware
{
    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        if ($this->parameter('common_site_enabled', 'yes') !== 'yes') {
            $renderer = $this->container->get('view');

            if (($path = realpath(THEME_DIR . '/' . $this->parameter('common_theme', 'default'))) !== false) {
                $renderer->getLoader()->addPath($path);
            }

            // add default errors pages
            $renderer->getLoader()->addPath(VIEW_ERROR_DIR);

            $response = (new Response())->withStatus(503);
            $response->getBody()->write($renderer->fetch('p503.twig'));

            return $response;
        }

        return $handler->handle($request);
    }
}
