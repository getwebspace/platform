<?php declare(strict_types=1);

namespace App\Domain\Service\Task\Exception;

use App\Domain\AbstractNotFoundException;

class TaskNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_TASK_NOT_FOUND';
}
