<?php declare(strict_types=1);

namespace App\Domain\Service\Task\Exception;

use App\Domain\AbstractException;

class TaskNotFoundException extends AbstractException
{
    protected $message = 'EXCEPTION_TASK_NOT_FOUND';
}
