<?php

namespace Reference;

class User
{
    // possible user statuses
    const STATUS_WORK   = 'work',
          STATUS_DELETE = 'delete',
          STATUS_BLOCK  = 'block';

    // list of statuses
    const STATUS = [
        self::STATUS_WORK   => 'Активный',
        self::STATUS_BLOCK  => 'Заблокирован',
        self::STATUS_DELETE => 'Удаленный',
    ];

    // possible user levels
    const LEVEL_USER    = 'user',
          LEVEL_ADMIN   = 'admin';

    // list of levels
    const LEVEL = [
        self::LEVEL_USER  => 'Пользователь',
        self::LEVEL_ADMIN => 'Администратор',
    ];
}
