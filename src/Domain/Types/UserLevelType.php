<?php declare(strict_types=1);

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class UserLevelType extends EnumType
{
    public const NAME = 'UserLevelType';

    public const LEVEL_USER = 'user';
    public const LEVEL_ADMIN = 'admin';
    public const LEVEL_DEMO = 'demo';

    public const LIST = [
        self::LEVEL_USER,
        self::LEVEL_ADMIN,
        self::LEVEL_DEMO,
    ];

    public const CUP_ACCESS = [
        self::LEVEL_ADMIN,
        self::LEVEL_DEMO,
    ];
}
