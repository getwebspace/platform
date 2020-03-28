<?php

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class TaskStatusType extends EnumType
{
    const NAME = 'TaskStatusType';

    const STATUS_QUEUE = 'queue',
          STATUS_WORK = 'work',
          STATUS_DONE = 'done',
          STATUS_FAIL = 'fail',
          STATUS_CANCEL = 'cancel',
          STATUS_DELETE = 'delete';

    const LIST          = [
        self::STATUS_QUEUE => 'В очереди',
        self::STATUS_WORK => 'В работе',
        self::STATUS_DONE => 'Завершена',
        self::STATUS_FAIL => 'Провалена',
        self::STATUS_CANCEL => 'Отменена',
        self::STATUS_DELETE => 'Удалена',
    ];
}
