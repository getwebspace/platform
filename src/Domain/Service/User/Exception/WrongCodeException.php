<?php declare(strict_types=1);

namespace App\Domain\Service\User\Exception;

use App\Domain\AbstractException;

class WrongCodeException extends AbstractException
{
    protected $message = 'EXCEPTION_WRONG_CODE';
}
