<?php declare(strict_types=1);

namespace App\Domain\Service\Notification\Exception;

use App\Domain\AbstractException;

class NotificationNotFoundException extends AbstractException
{
    protected $message = 'EXCEPTION_NOTIFICATION_NOT_FOUND';
}
