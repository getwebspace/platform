<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

class WrongPhoneValueException extends HttpBadRequestException
{
    protected string $title = 'Phone is wrong';

    protected string $description = 'Phone value format is wrong';
}
