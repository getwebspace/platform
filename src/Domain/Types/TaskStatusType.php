<?php declare(strict_types=1);

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class TaskStatusType extends EnumType
{
    public const NAME = 'TaskStatusType';

    public const STATUS_QUEUE = 'queue';
    public const STATUS_WORK = 'work';
    public const STATUS_DONE = 'done';
    public const STATUS_FAIL = 'fail';
    public const STATUS_CANCEL = 'cancel';
    public const STATUS_DELETE = 'delete';

    public const LIST          = [
        self::STATUS_QUEUE => 'В очереди',
        self::STATUS_WORK => 'В работе',
        self::STATUS_DONE => 'Завершена',
        self::STATUS_FAIL => 'Провалена',
        self::STATUS_CANCEL => 'Отменена',
        self::STATUS_DELETE => 'Удалена',
    ];
}
