<?php

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\UploadedFile;

class MultipartParserMiddleware extends AbstractMiddleware
{
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        $method = $request->getMethod();

        if (in_array($method, ['PUT', 'PATCH'])) {
            $contentType = $request->getHeaderLine('Content-Type');

            if (strpos($contentType, 'multipart/form-data') !== false) {
                $parsedData = $this->parseMultipart($request);

                if ($parsedData !== null) {
                    $request = $request
                        ->withParsedBody($parsedData['fields'])
                        ->withUploadedFiles($parsedData['files']);
                }
            }
        }

        return $handler->handle($request);
    }

    private function parseMultipart(ServerRequestInterface $request): ?array
    {
        $contentType = $request->getHeaderLine('Content-Type');

        // Extract boundary
        preg_match('/boundary=(.*)$/', $contentType, $matches);
        if (!isset($matches[1])) {
            return null;
        }

        $boundary = $matches[1];
        $rawBody = (string) $request->getBody();

        // Divided by boundary
        $parts = array_slice(explode("--$boundary", $rawBody), 1);

        $fields = [];
        $files = [];

        foreach ($parts as $part) {
            if (trim($part) === '--' || trim($part) === '') {
                continue;
            }

            // Separating headers and body
            $sections = explode("\r\n\r\n", $part, 2);
            if (count($sections) !== 2) {
                continue;
            }

            [$headerSection, $body] = $sections;

            // Cleaning up the last ones \r\n
            $body = substr($body, 0, -2);

            // Parsing headers
            $headers = $this->parseHeaders($headerSection);

            if (!isset($headers['content-disposition'])) {
                continue;
            }

            // Extract the field name
            preg_match('/name="([^"]*)"/', $headers['content-disposition'], $nameMatch);
            $name = $nameMatch[1] ?? null;

            if ($name === null) {
                continue;
            }

            // We check whether it is a file or a regular field
            preg_match('/filename="([^"]*)"/', $headers['content-disposition'], $filenameMatch);

            if (isset($filenameMatch[1]) && $filenameMatch[1] !== '') {
                // This is a file
                $filename = $filenameMatch[1];
                $mimeType = $headers['content-type'] ?? 'application/octet-stream';

                // Save to a temporary file
                $tmpFile = tempnam(sys_get_temp_dir(), 'upload_');
                file_put_contents($tmpFile, $body);

                $files[$name] = new UploadedFile(
                    $tmpFile,
                    $filename,
                    $mimeType,
                    filesize($tmpFile),
                    UPLOAD_ERR_OK,
                    true
                );
            } else {
                // Regular field
                $fields[$name] = $body;
            }
        }

        return [
            'fields' => $fields,
            'files' => $files
        ];
    }

    private function parseHeaders(string $headerSection): array
    {
        $headers = [];
        $lines = explode("\r\n", trim($headerSection));

        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $key = strtolower(trim($parts[0]));
                $value = trim($parts[1]);
                $headers[$key] = $value;
            }
        }

        return $headers;
    }
}
