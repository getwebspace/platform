<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractHttpException;

class HttpMethodNotAllowedException extends AbstractHttpException
{
    protected $code = 405;

    protected $message = 'Method Not Allowed.';

    protected string $title = '405 Method Not Allowed';

    protected string $description = 'The request method is not supported for the requested resource.';
}
