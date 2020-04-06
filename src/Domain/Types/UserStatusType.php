<?php declare(strict_types=1);

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class UserStatusType extends EnumType
{
    public const NAME = 'UserStatusType';

    public const STATUS_WORK   = 'work';
    public const STATUS_DELETE = 'delete';
    public const STATUS_BLOCK  = 'block';

    public const LIST = [
        self::STATUS_WORK   => 'Активный',
        self::STATUS_BLOCK  => 'Заблокирован',
        self::STATUS_DELETE => 'Удаленный',
    ];
}
