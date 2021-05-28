<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractHttpException;

class WrongIpValueException extends AbstractHttpException
{
    protected string $title = 'Ip is wrong';

    protected string $description = 'Ip value is wrong';
}
