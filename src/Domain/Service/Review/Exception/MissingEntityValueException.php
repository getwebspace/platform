<?php declare(strict_types=1);

namespace App\Domain\Service\Review\Exception;

use App\Domain\AbstractException;

class MissingEntityValueException extends AbstractException
{
    protected $message = 'EXCEPTION_ENTITY_MISSING';
}
