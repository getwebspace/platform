<?php declare(strict_types=1);

namespace App\Domain\Service\GuestBook\Exception;

use App\Domain\AbstractNotFoundException;

class EntryNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_ENTRY_NOT_FOUND';
}
