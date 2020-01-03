<?php

namespace App\Domain\References;

class User
{
    // possible address type
    const NEWSLETTER_TYPE_ALL = 'all',
          NEWSLETTER_TYPE_USERS = 'users',
          NEWSLETTER_TYPE_SUBSCRIBERS = 'subscribers';

    // list of address type
    const NEWSLETTER_TYPE = [
        self::NEWSLETTER_TYPE_ALL => 'Все',
        self::NEWSLETTER_TYPE_USERS => 'Пользователи',
        self::NEWSLETTER_TYPE_SUBSCRIBERS => 'Подписчики',
    ];
}
