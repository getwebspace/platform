<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Lets a shared cache keep a page, and only a page that is safe to share
 *
 * Only a successful GET/HEAD answer to an anonymous visitor is marked public. Errors,
 * redirects, signed-in visitors and responses that hand out a cookie (`?lang=`, a new
 * session) are never stored. The language depends on the cookie and on Accept-Language,
 * so those go into Vary.
 */
class PublicCacheMiddleware implements MiddlewareInterface
{
    public function __construct(private int $maxAge = 3600) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // whoever set the header explicitly knows better
        if ($response->hasHeader('Cache-Control')) {
            return $response;
        }

        if (!$this->isShareable($request, $response)) {
            return $response->withHeader('Cache-Control', 'private, no-store');
        }

        return $response
            ->withHeader('Cache-Control', 'public, max-age=' . $this->maxAge)
            ->withAddedHeader('Vary', 'Accept-Language, Cookie');
    }

    private function isShareable(ServerRequestInterface $request, ResponseInterface $response): bool
    {
        return in_array($request->getMethod(), ['GET', 'HEAD'], true)
            && $response->getStatusCode() === 200
            && $request->getAttribute('user', false) === false
            && !isset($request->getQueryParams()['lang'])
            && !$this->startsSession($request);
    }

    /**
     * A session opened by this very request means PHP is sending a Set-Cookie with it
     */
    private function startsSession(ServerRequestInterface $request): bool
    {
        return session_status() === PHP_SESSION_ACTIVE
            && !isset($request->getCookieParams()[session_name()]);
    }
}
