<?php declare(strict_types=1);

namespace App\Domain\Service\User\Exception;

use App\Domain\AbstractException;

class PhoneAlreadyExistsException extends AbstractException
{
    protected $message = 'EXCEPTION_PHONE_ALREADY_EXISTS';
}
