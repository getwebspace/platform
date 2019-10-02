<?php

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class UserLevelType extends EnumType
{
    const NAME = 'UserLevelType';

    const LEVEL_USER    = 'user',
          LEVEL_ADMIN   = 'admin',
          LEVEL_DEMO    = 'demo';

    const LIST = [
        self::LEVEL_USER  => 'Пользователь',
        self::LEVEL_ADMIN => 'Администратор',
        self::LEVEL_DEMO => 'Демо пользователь',
    ];

    const CUP_ACCESS = [
        self::LEVEL_ADMIN,
        self::LEVEL_DEMO,
    ];
}
