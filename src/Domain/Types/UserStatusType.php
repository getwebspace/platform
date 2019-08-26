<?php

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class UserStatusType extends EnumType
{
    const NAME = 'UserStatusType';

    const STATUS_WORK   = 'work',
          STATUS_DELETE = 'delete',
          STATUS_BLOCK  = 'block';

    const LIST = [
        self::STATUS_WORK   => 'Активный',
        self::STATUS_BLOCK  => 'Заблокирован',
        self::STATUS_DELETE => 'Удаленный',
    ];
}
