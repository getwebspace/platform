<?php declare(strict_types=1);

namespace App\Domain\Service\Reference\Exception;

use App\Domain\AbstractException;

class MissingTypeValueException extends AbstractException
{
    protected $message = 'EXCEPTION_ADDRESS_ALREADY_EXISTS';
}
