<?php

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class UserLevelType extends EnumType
{
    const NAME = 'UserLevelType';

    const LEVEL_USER    = 'user',
          LEVEL_ADMIN   = 'admin';

    const LIST = [
        self::LEVEL_USER  => 'Пользователь',
        self::LEVEL_ADMIN => 'Администратор',
    ];
}
