<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractHttpException;

class WrongAuthProviderException extends AbstractHttpException
{
    protected string $title = 'Auth provider is wrong';

    protected string $description = 'Not found';

    protected $message = 'EXCEPTION_WRONG_AUTH_PROVIDER';

    // the name of a provider comes from the request
    protected $code = 400;
}
