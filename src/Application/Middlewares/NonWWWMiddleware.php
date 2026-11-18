<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class NonWWWMiddleware extends AbstractMiddleware
{
    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->parameter('common_non_www', 'no') === 'yes') {
            $uri = $request->getUri();

            if (str_starts_with(mb_strtolower($uri->getHost()), 'www.')) {
                // the same address without the prefix, nothing else changes
                return (new Response())
                    ->withHeader('Location', (string) $uri->withHost(mb_substr($uri->getHost(), 4)))
                    ->withStatus(308);
            }
        }

        return $handler->handle($request);
    }
}
