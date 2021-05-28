<?php declare(strict_types=1);

namespace App\Domain\Service\GuestBook\Exception;

use App\Domain\AbstractException;

class EntryNotFoundException extends AbstractException
{
    protected $message = 'EXCEPTION_ENTRY_NOT_FOUND';
}
