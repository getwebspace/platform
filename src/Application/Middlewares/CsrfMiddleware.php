<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Application\Csrf;
use App\Domain\AbstractMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * A change in the admin panel (anything but a read) must carry the token of the signed in account,
 * in the `_csrf` field of the form or in the `X-CSRF-Token` header of a script
 *
 * Runs behind the access check, so a stranger is sent to the login page and never sees the refusal
 */
class CsrfMiddleware extends AbstractMiddleware
{
    public function __invoke(Request $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler->handle($request);
        }

        $body = $request->getParsedBody();
        $given = $request->getHeaderLine(Csrf::HEADER) ?: (is_array($body) ? ($body[Csrf::FIELD] ?? null) : null);

        if (Csrf::isValid($request->getAttribute('user', false), $given)) {
            return $handler->handle($request);
        }

        $response = new Response();
        $response->getBody()->write('Invalid or missing security token, reload the page and try again');

        return $response
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withStatus(403);
    }
}
