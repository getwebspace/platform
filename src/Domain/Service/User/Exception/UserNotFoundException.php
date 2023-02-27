<?php declare(strict_types=1);

namespace App\Domain\Service\User\Exception;

use App\Domain\AbstractNotFoundException;

class UserNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_USER_NOT_FOUND';
}
