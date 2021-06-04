<?php declare(strict_types=1);

namespace App\Domain\Service\Form\Exception;

use App\Domain\AbstractException;

class AddressAlreadyExistsException extends AbstractException
{
    protected $message = 'EXCEPTION_ADDRESS_ALREADY_EXISTS';
}
