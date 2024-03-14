<?php declare(strict_types=1);

namespace App\Domain\Casts\GuestBook;

use App\Domain\Casts\Enum;

class Status extends Enum
{
    public const WORK = 'work';
    public const MODERATE = 'moderate';

    public const LIST = [
        self::WORK,
        self::MODERATE,
    ];
}
