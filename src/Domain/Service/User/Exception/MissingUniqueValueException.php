<?php declare(strict_types=1);

namespace App\Domain\Service\User\Exception;

use App\Domain\AbstractException;

class MissingUniqueValueException extends AbstractException
{
    protected $message = 'EXCEPTION_UNIQUE_MISSING';
}
