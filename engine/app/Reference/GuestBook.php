<?php

namespace Reference;

class GuestBook
{
    // possible user statuses
    const STATUS_WORK   = 'work',
          STATUS_MODERATE = 'delete';

    // list of statuses
    const STATUS = [
        self::STATUS_WORK   => 'Активный',
        self::STATUS_MODERATE  => 'Модерируется',
    ];
}
