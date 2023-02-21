<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;

class CORSMiddleware extends AbstractMiddleware
{
    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        $headerAccept = $request->getHeaderLine('accept');

        if (str_contains($headerAccept, 'application/json') || $headerAccept === '*/*') {
            $response = $handler->handle($request);

            if (($value = $this->parameter('entity_cors_origin', false)) !== false) {
                $origin = $request->getHeaderLine('Origin');

                if ($origin && in_array($origin, explode(PHP_EOL, $value), true) || $value === '*') {
                    $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
                }
            }
            if (($value = $this->parameter('entity_cors_headers', false)) !== false) {
                $response = $response->withHeader('Access-Control-Allow-Headers', $value);
            }
            if (($value = $this->parameter('entity_cors_methods', false)) !== false) {
                $response = $response->withHeader('Access-Control-Allow-Methods', mb_strtoupper($value));
            }

            return $response;
        }

        return $handler->handle($request);
    }
}
