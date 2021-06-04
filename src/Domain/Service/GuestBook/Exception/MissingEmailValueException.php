<?php declare(strict_types=1);

namespace App\Domain\Service\GuestBook\Exception;

use App\Domain\AbstractException;

class MissingEmailValueException extends AbstractException
{
    protected $message = 'EXCEPTION_EMAIL_MISSING';
}
