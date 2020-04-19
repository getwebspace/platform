<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractException;

class WrongPhoneValueException extends AbstractException
{
    protected $title = 'Phone is wrong';

    protected $description = 'Phone value format is wrong';
}
