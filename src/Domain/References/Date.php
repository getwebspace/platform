<?php declare(strict_types=1);

namespace App\Domain\References;

class Date
{
    public const MINUTE = 60;
    public const HOUR   = 3600;
    public const DAY    = 86400;
    public const WEEK   = 604800;
    public const MONTH  = 2592000;
    public const YEAR   = 31536000;

    public const NULL_DATETIME = '0000-00-00 00:00:00';
    public const NULL_DATE     = '0000-00-00';
    public const NULL_TIME     = '00:00:00';
    public const DATE          = 'Y-m-d';
    public const DATETIME      = 'Y-m-d H:i:s';
    public const DATE_RUS      = 'd.m.Y';
    public const DATETIME_RUS  = 'd.m.Y H:i';
    public const TIME          = 'H:i:s';
}
