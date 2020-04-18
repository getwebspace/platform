<?php declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\AbstractEnumType;

class UserStatusType extends AbstractEnumType
{
    public const NAME = 'UserStatusType';

    public const STATUS_WORK = 'work';
    public const STATUS_DELETE = 'delete';
    public const STATUS_BLOCK = 'block';

    public const LIST = [
        self::STATUS_WORK,
        self::STATUS_BLOCK,
        self::STATUS_DELETE,
    ];
}
