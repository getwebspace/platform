<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractHttpException;

class HttpNotFoundException extends AbstractHttpException
{
    protected $code = 404;

    protected $message = 'Not found.';

    protected $title = '404 Not Found';

    protected $description = 'The requested resource could not be found. Please verify the URI and try again.';
}
