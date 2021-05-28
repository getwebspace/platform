<?php declare(strict_types=1);

namespace App\Domain\Service\Form\Exception;

use App\Domain\AbstractException;

class MissingMessageValueException extends AbstractException
{
    protected $message = 'EXCEPTION_MESSAGE_MISSING';
}
