<?php declare(strict_types=1);

namespace App\Application\Handlers;

use Slim\Exception\HttpException;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;

/**
 * Slim's default log line has no request data, so a 404/405 can't be traced to anything.
 * HTTP errors (bots, wrong method, missing page) are logged as one short warning with
 * method, URI and client address instead of an error with a framework-only stack trace.
 */
class ErrorHandler extends SlimErrorHandler
{
    protected function writeToErrorLog(): void
    {
        if (!$this->exception instanceof HttpException) {
            parent::writeToErrorLog();

            return;
        }

        $server = $this->request->getServerParams();
        $context = [
            'method' => $this->request->getMethod(),
            'uri' => (string) $this->request->getUri()->withUserInfo(''),
            'ip' => $server['HTTP_X_FORWARDED_FOR'] ?? $server['REMOTE_ADDR'] ?? '',
            'ua' => $this->request->getHeaderLine('User-Agent'),
        ];

        $this->logger->warning(
            sprintf('%d %s', $this->exception->getCode(), $this->exception->getMessage()),
            $context
        );
    }
}
