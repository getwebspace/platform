<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractHttpException;

class WrongEmailValueException extends AbstractHttpException
{
    protected string $title = 'Email is wrong';

    protected string $description = 'Email value is wrong';
}
