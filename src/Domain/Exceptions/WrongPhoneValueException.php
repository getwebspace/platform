<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractHttpException;

class WrongPhoneValueException extends AbstractHttpException
{
    protected string $title = 'Phone is wrong';

    protected string $description = 'Phone value format is wrong';

    protected $message = 'EXCEPTION_WRONG_PHONE';
}
