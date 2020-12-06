<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractException;

class WrongIpValueException extends AbstractException
{
    protected $title = 'Ip is wrong';

    protected $description = 'Ip value is wrong';
}
