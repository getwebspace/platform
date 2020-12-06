<?php declare(strict_types=1);

namespace App\Domain\References;

class User
{
    // possible address type
    public const NEWSLETTER_TYPE_ALL = 'all';
    public const NEWSLETTER_TYPE_USERS = 'users';
    public const NEWSLETTER_TYPE_SUBSCRIBERS = 'subscribers';

    // list of address type
    public const NEWSLETTER_TYPE = [
        self::NEWSLETTER_TYPE_ALL,
        self::NEWSLETTER_TYPE_USERS,
        self::NEWSLETTER_TYPE_SUBSCRIBERS,
    ];
}
