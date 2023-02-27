<?php declare(strict_types=1);

namespace App\Domain\Service\Parameter\Exception;

use App\Domain\AbstractNotFoundException;

class ParameterNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_PARAMETER_NOT_FOUND';
}
