<?php declare(strict_types=1);

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class GuestBookStatusType extends EnumType
{
    public const NAME = 'GuestBookStatusType';

    public const STATUS_WORK = 'work';
    public const STATUS_MODERATE = 'moderate';

    public const LIST          = [
        self::STATUS_WORK => 'Активный',
        self::STATUS_MODERATE => 'Модерируется',
    ];
}
