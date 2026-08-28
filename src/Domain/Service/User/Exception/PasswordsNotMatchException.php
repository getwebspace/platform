<?php declare(strict_types=1);

namespace App\Domain\Service\User\Exception;

use App\Domain\AbstractException;

class PasswordsNotMatchException extends AbstractException
{
    protected $message = 'EXCEPTION_PASSWORDS_NOT_MATCH';
}
