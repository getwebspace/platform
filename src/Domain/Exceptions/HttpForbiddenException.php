<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractHttpException;

class HttpForbiddenException extends AbstractHttpException
{
    protected $code = 403;

    protected $message = 'Forbidden.';

    protected string $title = '403 Forbidden';

    protected string $description = 'The request contained valid data and was understood by the server, but the server is refusing action.';
}
