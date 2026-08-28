<?php declare(strict_types=1);

namespace App\Domain\Service\ApiKey\Exception;

use App\Domain\AbstractNotFoundException;

class ApiKeyNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_API_KEY_NOT_FOUND';
}
