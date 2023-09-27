<?php declare(strict_types=1);

namespace App\Domain\Service\Notification\Exception;

use App\Domain\AbstractException;

class WrongTitleValueException extends AbstractException
{
    protected $message = 'EXCEPTION_WRONG_TITLE';
}
