<?php declare(strict_types=1);

namespace App\Domain\Casts\Task;

use App\Domain\Casts\Enum;

class Status extends Enum
{
    public const QUEUE = 'queue';
    public const WORK = 'work';
    public const DONE = 'done';
    public const FAIL = 'fail';
    public const CANCEL = 'cancel';
    public const DELETE = 'delete';

    public const LIST = [
        self::QUEUE,
        self::WORK,
        self::DONE,
        self::FAIL,
        self::CANCEL,
        self::DELETE,
    ];
}
