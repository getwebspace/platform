<?php

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class GuestBookStatusType extends EnumType
{
    const NAME = 'GuestBookStatusType';

    const STATUS_WORK = 'work',
          STATUS_MODERATE = 'moderate';

    const LIST          = [
        self::STATUS_WORK => 'Активный',
        self::STATUS_MODERATE => 'Модерируется',
    ];
}
