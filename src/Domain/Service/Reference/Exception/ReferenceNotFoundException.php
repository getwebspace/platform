<?php declare(strict_types=1);

namespace App\Domain\Service\Reference\Exception;

use App\Domain\AbstractNotFoundException;

class ReferenceNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_REFERENCE_NOT_FOUND';
}
