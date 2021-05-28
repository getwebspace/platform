<?php declare(strict_types=1);

namespace App\Domain\Service\GuestBook\Exception;

use App\Domain\AbstractException;

class WrongEmailValueException extends AbstractException
{
    protected $message = 'EXCEPTION_WRONG_EMAIL';
}
