<?php declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\AbstractEnumType;

class GuestBookStatusType extends AbstractEnumType
{
    public const NAME = 'GuestBookStatusType';

    public const STATUS_WORK = 'work';
    public const STATUS_MODERATE = 'moderate';

    public const LIST          = [
        self::STATUS_WORK,
        self::STATUS_MODERATE,
    ];
}
