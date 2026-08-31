<?php declare(strict_types=1);

namespace App\Domain\Service\Review\Exception;

use App\Domain\AbstractException;

class WrongEntityTypeValueException extends AbstractException
{
    protected $message = 'EXCEPTION_WRONG_ENTITY_TYPE';
}
