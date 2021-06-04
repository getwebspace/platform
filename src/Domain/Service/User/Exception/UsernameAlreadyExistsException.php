<?php declare(strict_types=1);

namespace App\Domain\Service\User\Exception;

use App\Domain\AbstractException;

class UsernameAlreadyExistsException extends AbstractException
{
    protected $message = 'EXCEPTION_USERNAME_ALREADY_EXISTS';
}
