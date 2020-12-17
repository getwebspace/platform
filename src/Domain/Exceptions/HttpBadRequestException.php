<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractHttpException;

class HttpBadRequestException extends AbstractHttpException
{
    protected $code = 400;

    protected $message = 'Bad request.';

    protected string $title = '400 Bad Request';

    protected string $description = 'The server cannot or will not process the request due to an apparent client error.';
}
