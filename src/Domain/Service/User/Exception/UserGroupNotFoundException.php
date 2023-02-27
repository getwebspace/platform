<?php declare(strict_types=1);

namespace App\Domain\Service\User\Exception;

use App\Domain\AbstractNotFoundException;

class UserGroupNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_USER_GROUP_NOT_FOUND';
}
