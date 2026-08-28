<?php declare(strict_types=1);

namespace App\Domain\Casts\ApiKey;

use App\Domain\Casts\Enum;

class Status extends Enum
{
    public const WORK = 'work';
    public const REVOKE = 'revoke';

    public const LIST = [
        self::WORK,
        self::REVOKE,
    ];
}
