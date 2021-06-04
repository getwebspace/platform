<?php declare(strict_types=1);

namespace App\Domain\Service\Task\Exception;

use App\Domain\AbstractException;

class MissingActionValueException extends AbstractException
{
    protected $message = 'EXCEPTION_ACTION_VALUE_MISSING';
}
