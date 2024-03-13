<?php declare(strict_types=1);

namespace App\Domain\Casts\User;

use App\Domain\Casts\Enum;

class Status extends Enum
{
    public const WORK = 'work';
    public const DELETE = 'delete';
    public const BLOCK = 'block';

    public const LIST = [
        self::WORK,
        self::DELETE,
        self::BLOCK,
    ];
}
