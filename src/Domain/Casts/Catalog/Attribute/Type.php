<?php declare(strict_types=1);

namespace App\Domain\Casts\Catalog\Attribute;

use App\Domain\Casts\Enum;

class Type extends Enum
{
    public const STRING = 'string';
    public const INTEGER = 'integer';
    public const FLOAT = 'float';
    public const BOOLEAN = 'boolean';

    public const LIST = [
        self::STRING,
        self::INTEGER,
        self::FLOAT,
        self::BOOLEAN,
    ];
}
