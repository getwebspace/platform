<?php declare(strict_types=1);

namespace App\Domain\Casts\Page;

use App\Domain\Casts\Enum;

class Type extends Enum
{
    public const HTML = 'html';
    public const TEXT = 'text';

    public const LIST = [
        self::HTML,
        self::TEXT,
    ];
}
