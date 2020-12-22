<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

class WrongIpValueException extends HttpBadRequestException
{
    protected string $title = 'Ip is wrong';

    protected string $description = 'Ip value is wrong';
}
