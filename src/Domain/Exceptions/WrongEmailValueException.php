<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractException;

class WrongEmailValueException extends AbstractException
{
    protected $title = 'Email is wrong';

    protected $description = 'Email value is wrong';
}
