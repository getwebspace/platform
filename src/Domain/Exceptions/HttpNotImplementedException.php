<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractHttpException;

class HttpNotImplementedException extends AbstractHttpException
{
    protected $code = 501;

    protected $message = 'Not Implemented.';

    protected $title = '403 Not Implemented';

    protected $description = 'The server either does not recognize the request method, or it lacks the ability to fulfil the request.';
}
