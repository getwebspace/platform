<?php declare(strict_types=1);

namespace App\Domain\Service\Parameter\Exception;

use App\Domain\AbstractException;

class ParameterNotFoundException extends AbstractException
{
    protected $message = 'EXCEPTION_PARAMETER_NOT_FOUND';
}
