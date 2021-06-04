<?php declare(strict_types=1);

namespace App\Domain\Service\User\Exception;

use App\Domain\AbstractException;

class EmailAlreadyExistsException extends AbstractException
{
    protected $message = 'EXCEPTION_EMAIL_ALREADY_EXISTS';
}
