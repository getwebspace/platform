<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

class WrongEmailValueException extends HttpBadRequestException
{
    protected string $title = 'Email is wrong';

    protected string $description = 'Email value is wrong';
}
