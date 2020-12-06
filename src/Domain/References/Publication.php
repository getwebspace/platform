<?php declare(strict_types=1);

namespace App\Domain\References;

class Publication
{
    // possible order by
    public const ORDER_BY_TITLE = 'title';
    public const ORDER_BY_DATE = 'date';

    // list of order by
    public const ORDER_BY = [
        self::ORDER_BY_DATE,
        self::ORDER_BY_TITLE,
    ];

    // possible order directions
    public const ORDER_DIRECTION_DESC = 'DESC';
    public const ORDER_DIRECTION_ASC = 'ASC';

    // list of order directions
    public const ORDER_DIRECTION = [
        self::ORDER_DIRECTION_DESC,
        self::ORDER_DIRECTION_ASC,
    ];
}
