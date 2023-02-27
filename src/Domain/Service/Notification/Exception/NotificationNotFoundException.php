<?php declare(strict_types=1);

namespace App\Domain\Service\Notification\Exception;

use App\Domain\AbstractNotFoundException;

class NotificationNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_NOTIFICATION_NOT_FOUND';
}
