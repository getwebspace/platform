<?php declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\AbstractEnumType;

class TaskStatusType extends AbstractEnumType
{
    public const NAME = 'TaskStatusType';

    public const STATUS_QUEUE = 'queue';
    public const STATUS_WORK = 'work';
    public const STATUS_DONE = 'done';
    public const STATUS_FAIL = 'fail';
    public const STATUS_CANCEL = 'cancel';
    public const STATUS_DELETE = 'delete';

    public const LIST = [
        self::STATUS_QUEUE,
        self::STATUS_WORK,
        self::STATUS_DONE,
        self::STATUS_FAIL,
        self::STATUS_CANCEL,
        self::STATUS_DELETE,
    ];
}
