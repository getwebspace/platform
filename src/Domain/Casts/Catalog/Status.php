<?php declare(strict_types=1);

namespace App\Domain\Casts\Catalog;

use App\Domain\Casts\Enum;

class Status extends Enum
{
    public const WORK = 'work';
    public const DELETE = 'delete';

    public const LIST = [
        self::WORK,
        self::DELETE,
    ];
}
