<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class NonWWWMiddleware extends AbstractMiddleware
{
    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        if ($this->parameter('common_non_www', 'no') === 'yes') {
            $scheme = $request->getUri()->getScheme();
            $host = $request->getUri()->getHost();

            if (str_starts_with($host, 'www')) {
                return (new Response())
                    ->withHeader('Location', $scheme . '://' . str_replace('www.', '', $host))
                    ->withStatus(307);
            }
        }

        return $handler->handle($request);
    }
}
