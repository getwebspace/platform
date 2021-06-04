<?php declare(strict_types=1);

namespace App\Domain\Service\Parameter\Exception;

use App\Domain\AbstractException;

class ParameterAlreadyExistsException extends AbstractException
{
    protected $message = 'EXCEPTION_PARAMETER_ALREADY_EXISTS';
}
